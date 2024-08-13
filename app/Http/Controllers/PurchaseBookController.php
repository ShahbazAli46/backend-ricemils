<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseBook;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class PurchaseBookController extends Controller
{
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

            $purchase_book = PurchaseBook::with(['product:id,product_name','supplier:id,person_name'])->whereBetween('date', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $purchase_book]);
        }else{
            $purchase_book=PurchaseBook::with(['product:id,product_name','supplier:id,person_name'])->get();
            return response()->json(['data' => $purchase_book]);
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
            'sup_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'pro_id' => ['required','exists:products,id',new ExistsNotSoftDeleted('products')],
            'quantity' => 'required|numeric|min:1',
            'price' => 'required|numeric|min:1',
            'truck_no' => 'nullable|string|max:50',
            'packing_type' => 'required|in:add,return,paid',
            'date' => 'nullable|date',
            'payment_type' => 'required|in:cash,cheque,both',
            'first_weight' => 'required|numeric|min:1',
            'second_weight' => 'nullable|numeric|min:1',
            'net_weight' => 'required|numeric|min:1',
            'packing_weight' => 'required|numeric|min:1',
            'final_weight' => 'required|numeric|min:1',
        ];        

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|min:1';
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
            $rules['cash_amount']= 'required|numeric|min:1';
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            $supplier=Customer::where(['id'=>$request->sup_id,'customer_type'=>'supplier'])->first();
            if(!$supplier){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $product=Product::where(['id'=>$request->pro_id,'product_type'=>'paddy'])->first();
            if(!$product){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            // Start a transaction
            DB::beginTransaction();
            $total_amount=$request->input('price')* $request->input('quantity');
            $add_amount=0;
            $cash_amount= ($request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $cheque_amount= ($request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            
            $add_amount+= ($cash_amount+$cheque_amount);
            $rem_amount=$add_amount-$total_amount;

            $purchaseBook = PurchaseBook::create([
                'sup_id' => $request->sup_id,
                'pro_id' => $request->pro_id,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'truck_no' => $request->truck_no,
                'packing_type' => $request->packing_type,
                'date' => $request->date,
                'payment_type' => $request->payment_type,
                'bank_id' => $request->bank_id,
                'cash_amount' => $cash_amount,
                'cheque_amount' => $cheque_amount,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'total_amount' => $total_amount,
                'rem_amount' => $rem_amount,
                'first_weight' => $request->first_weight,
                'second_weight' => $request->second_weight,
                'net_weight' => $request->net_weight,
                'packing_weight' => $request->packing_weight,
                'final_weight' => $request->final_weight,
            ]);


            if (!$purchaseBook) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Add Purchase Order.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            
            //add opening balance transection in ledger
            $transactionData=['customer_id'=>$request->sup_id,'bank_id'=>$request->bank_id,'description'=>null,'dr_amount'=>$total_amount,'cr_amount'=>$add_amount,
            'adv_amount'=>0.00,'cash_amount'=>$cash_amount,'payment_type'=>$request->payment_type,'cheque_amount'=>$cheque_amount,
            'cheque_no'=>$request->cheque_no,'cheque_date'=>$request->cheque_date,'customer_type'=>'supplier','book_id'=>$purchaseBook->id,'entry_type'=>'dr&cr','balance'=>$rem_amount];
            $res=$purchaseBook->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Something Went Wrong Please Try Again Later.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            // Commit the transaction
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order Added Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Purchase Order. ' . $e->getMessage(),
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
            $purchase_book = PurchaseBook::with(['product:id,product_name','supplier:id,person_name'])->findOrFail($id);
            return response()->json(['data' => $purchase_book]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Purchase Order Not Found.'], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'sup_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'pro_id' => ['required','exists:products,id',new ExistsNotSoftDeleted('products')],
            'quantity' => 'required|numeric|min:1',
            'price' => 'required|numeric|min:1',
            'truck_no' => 'nullable|string|max:50',
            'packing_type' => 'required|in:add,return,paid',
            'date' => 'nullable|date',
            'payment_type' => 'required|in:cash,cheque,both',
            'first_weight' => 'required|numeric|min:1',
            'second_weight' => 'nullable|numeric|min:1',
            'net_weight' => 'required|numeric|min:1',
            'packing_weight' => 'required|numeric|min:1',
            'final_weight' => 'required|numeric|min:1',
        ];        

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|min:1';
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
            $rules['cash_amount']= 'required|numeric|min:1';
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            $purchaseBook = PurchaseBook::findOrFail($id);

            $supplier=Customer::where(['id'=>$request->sup_id,'customer_type'=>'supplier'])->first();
            if(!$supplier){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $product=Product::where(['id'=>$request->pro_id,'product_type'=>'paddy'])->first();
            if(!$product){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            // Start a transaction
            DB::beginTransaction();

            $total_amount=$request->input('price')* $request->input('quantity');
            $add_amount=0;
            $cash_amount= ($request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $cheque_amount= ($request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            
            $add_amount+= ($cash_amount+$cheque_amount);
            $rem_amount=$add_amount-$total_amount;

            $purchaseBook->update([
                'sup_id' => $request->sup_id,
                'pro_id' => $request->pro_id,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'truck_no' => $request->truck_no,
                'packing_type' => $request->packing_type,
                'date' => $request->date,
                'payment_type' => $request->payment_type,
                'bank_id' => $request->bank_id,
                'cash_amount' => $cash_amount,
                'cheque_amount' => $cheque_amount,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'total_amount' => $total_amount,
                'rem_amount' => $rem_amount,
                'first_weight' => $request->first_weight,
                'second_weight' => $request->second_weight,
                'net_weight' => $request->net_weight,
                'packing_weight' => $request->packing_weight,
                'final_weight' => $request->final_weight,
            ]);

            if (!$purchaseBook) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Update Purchase Order.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }
            $purchaseBook->ledger()->delete();
          
            //add opening balance transection in ledger
            $transactionData=['customer_id'=>$request->sup_id,'bank_id'=>$request->bank_id,'description'=>null,'dr_amount'=>$total_amount,'cr_amount'=>$add_amount,
            'adv_amount'=>0.00,'cash_amount'=>$cash_amount,'payment_type'=>$request->payment_type,'cheque_amount'=>$cheque_amount,
            'cheque_no'=>$request->cheque_no,'cheque_date'=>$request->cheque_date,'customer_type'=>'supplier','book_id'=>$purchaseBook->id,'entry_type'=>'dr&cr','balance'=>$rem_amount];
            
            $res=$purchaseBook->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Something Went Wrong Please Try Again Later.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            // Commit the transaction
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Purchase Order Updated Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Purchase Order Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message' => 'Failed to Update Purchase Order. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = PurchaseBook::findOrFail($id);
            $sup_id=$resource->sup_id;
            $resource->delete();
            $resource->reCalculate($sup_id);

            return response()->json(['status'=>'success','message' => 'Purchase Order Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Purchase Order Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
