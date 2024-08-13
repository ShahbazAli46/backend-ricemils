<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;


class SupplierLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        if($request->has('sup_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','supplier')->where('id',$request->sup_id)->first();
            if($customer){
                if($request->has('start_date') && $request->has('end_date')){
                    $startDate = $request->input('start_date');
                    $endDate = $request->input('end_date');
                    $customer->ledgers = $customer->ledgers()->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers = $customer->ledgers()->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Supplier Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
    
                $supplier_ledger = CustomerLedger::with(['customer:id,person_name'])->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $supplier_ledger]);
            }else{
                $supplier_ledger =CustomerLedger::with(['customer:id,person_name'])->get();
                return response()->json(['data' => $supplier_ledger]);
            }
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
            'payment_type' => 'required|in:cash,cheque,both',
            'description' => 'nullable|string',
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

            $add_amount=0;
            $cash_amount= ($request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $cheque_amount= ($request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            
            $add_amount+= ($cash_amount+$cheque_amount);

            DB::beginTransaction();

            $transactionData=['customer_id'=>$request->sup_id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$add_amount,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'supplier','book_id'=>null,'entry_type'=>'cr','balance'=>$add_amount];
            
            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
            }else if($request->input('payment_type') == 'cash'){
                $transactionData['cash_amount']= $cash_amount;
            }else{
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
                $transactionData['cash_amount']= $cash_amount;
            }
            
            $res=$supplier->addTransaction($transactionData);

            if(!$res){
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Create Supplier Ledger.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Supplier Ledger Created Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Supplier Ledger. ' . $e->getMessage(),
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
            $supplier_ledger = CustomerLedger::with(['customer:id,person_name'])->findOrFail($id);
            return response()->json(['data' => $supplier_ledger]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Supplier Ledger Not Found.'], Response::HTTP_NOT_FOUND);
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
            'payment_type' => 'required|in:cash,cheque,both',
            'description' => 'nullable|string',
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
            $supplier_ledger = CustomerLedger::where('customer_type','supplier')->where('id',$id)->firstOrFail();
           
            $add_amount=0;
            $cash_amount= ($request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $cheque_amount= ($request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            $add_amount+= ($cash_amount+$cheque_amount);

            DB::beginTransaction();
            
            $transactionData=['id'=>$supplier_ledger->id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$add_amount,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'supplier','book_id'=>null,'entry_type'=>'cr','balance'=>$add_amount];
           
            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
            }else if($request->input('payment_type') == 'cash'){
                $transactionData['cash_amount']= $cash_amount;
            }else{
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
                $transactionData['cash_amount']= $cash_amount;
            }
            
            $res=$supplier_ledger->updateTransaction($transactionData);

            if($res->original['status']=='success'){
                DB::commit();
                return response()->json($res->original, Response::HTTP_OK); // 200 OK
            }else{
                DB::rollBack();
                return response()->json($res->original, Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Supplier Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message' => 'Failed to Update Supplier. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = CustomerLedger::where('customer_type','supplier')->where('id',$id)->firstOrFail();
            $res=$resource->deleteTransection($id);
            return response()->json($res->original);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Supplier Ledger Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
