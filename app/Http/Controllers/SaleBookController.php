<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Packing;
use App\Models\Product;
use App\Models\ProductStock;
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
            'pro_id' => ['required','exists:products,id'],
            'packing_id' => ['required','exists:packings,id'],
            'price' => 'required|numeric|min:1',
            'quantity' => 'required|numeric|min:1',
            'product_description' => 'nullable|string',
        ];        

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            $nextRes=$this->getNextRefNo();
            if($request->id!=$nextRes->original['next_id']){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ref Id is Invalid.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            DB::beginTransaction();

            // Validate buyer existence
            $buyer = Customer::where(['id' => $request->buyer_id, 'customer_type' => 'buyer'])->first();
            if (!$buyer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Buyer Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        
            // Validate product existence
            $product = Product::where(['id' => $request->pro_id, 'product_type' => 'other'])->firstOrFail();
            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Type Is Not Valid.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        
            // Validate packing
            $packing = Packing::find($request->packing_id);
            $productStock = ProductStock::where([
                'product_id' => $product->id,
                'packing_id' => $packing->id,
            ])->first();

            if(!$productStock){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Stock is Empty.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);  
            }

            if ($productStock->quantity < $request->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product ' . $product->product_name . ' Out of Stock.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
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
                ]
            );
        
            // Ensure SaleBook was created or found
            if (!$saleBook) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Add Sale Order.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
           
            // Create or update SaleBookDetail
            $saleBookDetail = SaleBookDetail::updateOrCreate(
                [
                    'sale_book_id' => $saleBook->id,
                    'pro_id' => $request->pro_id,
                    'packing_id' => $request->packing_id,
                    'pro_stock_id' => $productStock->id,
                ],
                [
                    'product_name' => $product->product_name,
                    'product_description' => $product->product_description,
                    'packing_size' => $packing->packing_size,
                    'packing_unit' => $packing->packing_unit,
                    'quantity' => $request->quantity,
                    'price' => $request->price,
                    'total_amount' => ($request->quantity * $request->price),
                ]
            );
            
            // Check if SaleBookDetail was created or updated
            if (!$saleBookDetail) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Add Item on Sale Order.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        
            // Recalculate and update total amount of SaleBook
            $totalAmount = SaleBookDetail::where('sale_book_id', $saleBook->id)
                ->sum('total_amount');
            $saleBook->total_amount = $totalAmount;
            $saleBook->save();

            $saleBookObj = SaleBook::with(['details'])->find($request->id);

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Item Successfully Added to Sale Order.',
                'data' => $saleBookObj,
            ], Response::HTTP_CREATED);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Sale Order. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
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
                return response()->json([
                    'status' => 'success',
                    'message' => 'Item Removed Successfully.',
                    'data' => $saleBook,
                ], Response::HTTP_CREATED);
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
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $sale_book = SaleBook::with(['details','buyer:id,person_name'])->whereBetween('date', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $sale_book]);
        }else{
            $sale_book=SaleBook::with(['details','supplier:id,person_name'])->get();
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
        ];  

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            DB::beginTransaction();

            $saleBook=SaleBook::with(['details'])->where(['id'=>$request->sale_book_id,'order_status'=>'cart'])->first();
            if($saleBook){
                if($saleBook->details()->count()<=0){
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Sale Book Cart is Empty.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $saleBook->order_status='completed';
                $saleBook->save();
                $saleBook->details()->update(['order_status' => 'completed']);

                $transactionData=['customer_id'=>$saleBook->buyer_id,'bank_id'=>null,'description'=>null,'dr_amount'=>$saleBook->total_amount,'cr_amount'=>0.00,
                'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>'Cash','cheque_amount'=>0.00,
                'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'buyer','book_id'=>$saleBook->id,'entry_type'=>'dr','balance'=>$saleBook->total_amount];
                
                $res=$saleBook->addTransaction($transactionData);
                if(!$res){
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Something Went Wrong Please Try Again Later.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }

                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Order Completed Successfully.',
                ], Response::HTTP_CREATED);
            }else{
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sale Book Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Sale Order. ' . $e->getMessage(),
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
            $sale_book = SaleBook::with(['details','buyer:id,person_name'])->findOrFail($id);
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
        // try {
        //     $resource = SaleBook::with(['details'])->findOrFail($id);
        //     foreach($resource->details as $sale_detail){
        //         $productStock=ProductStock::where(['product_id'=>$sale_detail->pro_id,'packing_id'=>$sale_detail->packing_id])->first();
        //         if($productStock){
        //             $productStock->update(['quantity'=>($productStock->quantity+$sale_detail->quantity)]);
        //         }
        //     }

        //     $sup_id=$resource->sup_id;
        //     $resource->delete();
        //     $resource->reCalculate($sup_id);

        //     return response()->json(['status'=>'success','message' => 'Purchase Order Deleted Successfully']);
        // } catch (ModelNotFoundException $e) {
        //     return response()->json(['status'=>'error', 'message' => 'Purchase Order Not Found.'], Response::HTTP_NOT_FOUND);
        // } catch (Exception $e) {
        //     return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        // } 
    }
}