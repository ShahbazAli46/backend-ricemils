<?php

namespace App\Http\Controllers;

use App\Models\CompanyProductStock;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Packing;
use App\Models\Product;
use App\Models\SaleBook;
use App\Models\SaleBookDetail;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class SaleBookController extends Controller
{
    public function getNextRefNo(){
        $saleBook=SaleBook::where('order_status','cart')->first();
        if($saleBook){
            return response()->json(['next_id'=>$saleBook->id,'next_ref_no' => $saleBook->ref_no]);
        }else{
            $nextId = DB::select("SHOW TABLE STATUS LIKE 'sale_book'");
            $nextId = $nextId[0]->Auto_increment;
            $next_ref_no = 'SB-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            return response()->json(['next_id'=>$nextId,'next_ref_no' => $next_ref_no]);
        }
    }

    public function AddItem(Request $request){
        $rules = [
            'id' => ['required','numeric'],
            'buyer_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'truck_no' => 'nullable|string|max:50',
            'reference_no' => 'nullable|string|max:50',
            'pro_id' => ['required','exists:products,id'],
            'price_mann' => 'required|numeric|min:1',
            'weight' => 'required|numeric|min:1',
            'khoot' => 'required|numeric|min:0',
            'bardaana_deduction' => 'required|numeric|min:0',
            'salai_amt_per_bag' => 'required|numeric|min:0',
            'bardaana_quantity' => 'required|numeric|min:0',
            'product_description' => 'nullable|string',
        ];        

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            $nextRes=$this->getNextRefNo();
            if($request->id!=$nextRes->original['next_id']){
                return response()->json(['status' => 'error','message' => 'Ref Id is Invalid.',], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            DB::beginTransaction();

            // Validate buyer existence
            $buyer = Customer::where(['id' => $request->buyer_id, 'customer_type' => 'party'])->first();
            if (!$buyer) {
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Party Does Not Exist.',], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        
            // Validate product existence
            $product = Product::where(['id' => $request->pro_id])->firstOrFail();
            if (!$product) {
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Product Type Is Not Valid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $net_weight=($request->weight-($request->khoot+$request->bardaana_deduction));
            $price = ($request->price_mann * $net_weight) / 40;

            //check company stock
            $stock = CompanyProductStock::where(['product_id'=> $request->pro_id])->latest()->first();
            $old_remaining_weight=$stock?$stock->remaining_weight:0;
            if($old_remaining_weight<$net_weight){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Out of Stock, Porduct '.$product->product_name], 422); // Use 422 Unprocessable Entity
            }
        
            // Create or update SaleBook
            $saleBook = SaleBook::updateOrCreate(
                [
                    'id' => $request->id,
                    // 'order_status' => 'cart',
                ],
                [
                    'buyer_id' => $request->buyer_id, // use buyer_id here
                    'truck_no' => $request->truck_no,
                    'reference_no' => $request->reference_no,
                ]
            );
        
            // Ensure SaleBook was created or found
            if (!$saleBook) {
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Add Sale Order.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $total_salai_amt=($request->salai_amt_per_bag*$request->bardaana_quantity);

            // Create or update SaleBookDetail
            $saleBookDetail = SaleBookDetail::updateOrCreate(
                [
                    'sale_book_id' => $saleBook->id,
                    'pro_id' => $request->pro_id,
                ],
                [
                    'product_name' => $product->product_name,
                    'product_description' => $product->product_description,
                    'price_mann' => $request->price_mann,
                    'weight' => $request->weight,
                    'khoot' => $request->khoot,
                    'bardaana_deduction' => $request->bardaana_deduction,
                    'net_weight' => $net_weight,
                    'price' => $price,
                    'salai_amt_per_bag' => $request->salai_amt_per_bag,
                    'bardaana_quantity' => $request->bardaana_quantity,
                    'total_salai_amt' => $total_salai_amt,
                    'total_amount' => $total_salai_amt+$price,
                ]
            );
            
            // Check if SaleBookDetail was created or updated
            if (!$saleBookDetail) {
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Add Item on Sale Order.',], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        
            // Recalculate and update total amount of SaleBook
            $totalAmount = SaleBookDetail::where('sale_book_id', $saleBook->id)->sum('total_amount');
            $saleBook->total_amount = $totalAmount;
            $saleBook->save();

            $saleBookObj = SaleBook::with(['details'])->find($request->id);

            DB::commit();
            return response()->json(['status' => 'success','message' => 'Item Successfully Added to Sale Order.','data' => $saleBookObj,], Response::HTTP_CREATED);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Sale Order. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    public function RemoveItem($id){
        try {
            DB::beginTransaction();
            $saleBookDetail=SaleBookDetail::where(['id'=>$id,'order_status'=>'cart'])->first();
            if($saleBookDetail){
                $sale_book_id=$saleBookDetail->sale_book_id;
                $saleBookDetail->delete();
    
                $saleBook = SaleBook::with(['details'])->find($sale_book_id);
    
                // Recalculate and update total amount of SaleBook
                $totalAmount = SaleBookDetail::where('sale_book_id', $sale_book_id)->sum('total_amount');
                $saleBook->total_amount = $totalAmount;
                $saleBook->save();
                
                DB::commit();
                return response()->json(['status' => 'success','message' => 'Item Removed Successfully.','data' => $saleBook,], Response::HTTP_CREATED);
            }else{
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sale Book Detail Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Sale Order. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    public function ClearItems($id){
        $saleBook = SaleBook::with(['details'])->where(['id'=>$id,'order_status'=>'cart'])->first();
        if($saleBook){
            SaleBookDetail::where(['sale_book_id'=>$saleBook->id,'order_status'=>'cart'])->delete();
            $saleBook->total_amount = 0.00;
            $saleBook->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Cart Clear Successfully.',
            ], Response::HTTP_CREATED);
        }else{
            return response()->json([
                'status' => 'error',
                'message' => 'Sale Book Does Not Exist.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->has('start_date') && $request->has('end_date')){
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();

            $sale_book = SaleBook::with(['details','party:id,person_name'])->whereBetween('date', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $sale_book]);
        }else{
            $sale_book=SaleBook::with(['details','party:id,person_name'])->get();
            return response()->json(['data' => $sale_book]);
        }
    }

     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'sale_book_id' => ['required','exists:sale_book,id'],
            'description'=> 'nullable|string',
        ];  

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            DB::beginTransaction();

            $saleBook=SaleBook::with(['details'])->where(['id'=>$request->sale_book_id,'order_status'=>'cart'])->first();
            if($saleBook){
                if($saleBook->details()->count()<=0){
                    return response()->json(['status' => 'error','message' => 'Sale Book Cart is Empty.',], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                //check company stock weight
                $sale_book_details=$saleBook->details()->get();
                foreach($sale_book_details as $detail){
                    $stock = CompanyProductStock::where(['product_id'=> $detail->pro_id])->latest()->first();
                    $old_remaining_weight=$stock?$stock->remaining_weight:0;
                    if($old_remaining_weight<$detail->net_weight){
                        DB::rollBack();
                        return response()->json(['status' => 'error','message' => 'Out of Stock #'.$detail->pro_id], 422); // Use 422 Unprocessable Entity
                    }
                }

                $saleBook->order_status='completed';
                $saleBook->description=$request->description;
                $saleBook->save();
                $saleBook->details()->update(['order_status' => 'completed']);

                $buyer=Customer::find($saleBook->buyer_id);
                $lastLedger = $buyer->ledgers()->orderBy('id', 'desc')->first();
                
                $previousBalance=0.00;
                if($lastLedger){
                    $previousBalance=$lastLedger->balance;
                }else{
                    $previousBalance=$buyer->opening_balance;
                }
                $totalWithPreBlnc=$previousBalance+$saleBook->total_amount;

                $transactionData=['customer_id'=>$saleBook->buyer_id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$saleBook->total_amount,
                'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>'Cash','cheque_amount'=>0.00,
                'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'party','book_id'=>$saleBook->id,'entry_type'=>'cr','balance'=>$totalWithPreBlnc];
                
                $res=$saleBook->addTransaction($transactionData);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.',], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }

                // Add company stock entry
                foreach($sale_book_details as $detail){
                    $stock = CompanyProductStock::where(['product_id'=> $detail->pro_id])->latest()->first();
                    $old_total_weight=$stock?$stock->total_weight:0;
                    $old_remaining_weight=$stock?$stock->remaining_weight:0;
                    CompanyProductStock::create([
                        'product_id' => $detail->pro_id,
                        'total_weight' => $old_total_weight,
                        'stock_out' => $detail->net_weight,
                        'remaining_weight' =>  $old_remaining_weight-$detail->net_weight,
                        'linkable_id' => $detail->id,
                        'linkable_type' => 'App\Models\SaleBookDetail',
                        'entry_type' => 'sale',
                        'price' => $detail->price,
                        'price_mann' => $detail->price_mann,
                        'total_amount' => $detail->total_amount,
                    ]);
                }

                DB::commit();
                return response()->json(['status' => 'success','message' => 'Order Completed Successfully.',], Response::HTTP_CREATED);
            }else{
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Sale Book Does Not Exist.',], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Sale Order. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $sale_book = SaleBook::with(['details','party'])->findOrFail($id);
            return response()->json(['data' => $sale_book]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Sale Order Not Found.'], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $resource = SaleBook::with(['details'])->findOrFail($id);
            $customer_ledger=CustomerLedger::where('book_id',$resource->id)->first();
            $resource->details()->delete();
            $resource->delete();
            $resource->deleteTransection($customer_ledger->id);
            DB::commit();
            return response()->json(['status'=>'success','message' => 'Sale Order Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Sale Order Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
