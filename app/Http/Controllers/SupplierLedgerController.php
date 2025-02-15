<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanyLedger;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Rules\CheckBankBalance;
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
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                    $customer->ledgers= $customer->load(['ledgers' => function($query) use ($startDate, $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]) ->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers= $customer->load(['ledgers' => function($query) {
                        $query->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->where('customer_type','supplier')->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Supplier Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
    
                $supplier_ledger = CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','supplier')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $supplier_ledger]);
            }else{
                $supplier_ledger =CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','supplier')->get();
                return response()->json(['data' => $supplier_ledger]);
            }
        }
    }
    
    public function getSupplierPaidAmount(Request $request)
    {   
        if($request->has('sup_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','supplier')->where('id',$request->sup_id)->first();
            if($customer){
                if($request->has('start_date') && $request->has('end_date')){
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                    $customer->ledgers= $customer->load(['ledgers' => function($query) use ($startDate, $endDate) {
                        $query->where(function ($query) {
                            $query->where('entry_type', 'cr')->orWhere('entry_type', 'dr&cr');
                        })->whereBetween('created_at', [$startDate, $endDate])->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers= $customer->load(['ledgers' => function($query) {
                        $query->where(function ($query) {
                            $query->where('entry_type', 'cr')->orWhere('entry_type', 'dr&cr');
                        })->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Supplier Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
    
                $supplier_ledger = CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])
                ->where(function ($query) {
                    $query->where('entry_type', 'cr')->orWhere('entry_type', 'dr&cr');
                })->where('customer_type','supplier')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $supplier_ledger]);
            }else{
                $supplier_ledger =CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])
                ->where(function ($query) {
                    $query->where('entry_type', 'cr')->orWhere('entry_type', 'dr&cr');
                })->where('customer_type','supplier')->get();
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
            'payment_type' => 'required|in:cash,cheque,both,online',
            'description' => 'nullable|string',
        ];        

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';

            if($request->cash_amount>=1){
                $rules['cheque_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cheque_amount']= ['required','numeric','not_in:0'];
            }

            $rules['bank_tax']= 'required|numeric|min:0';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|not_in:0';
        }else if($request->input('payment_type') == 'online'){
            if($request->cash_amount>=1){
                $rules['cash_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cash_amount']= ['required','numeric','not_in:0'];
            }
            $rules['transection_id']= 'required|string|max:100';
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['bank_tax']= 'required|numeric|min:0';
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            if($request->cash_amount>=1){
                $rules['cheque_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cheque_amount']= ['required','numeric','not_in:0'];
            }

            $rules['cash_amount']= 'required|numeric|not_in:0';
            $rules['bank_tax']= 'required|numeric|min:0';
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $payment_type=$request->input('payment_type');
            $supplier=Customer::where(['id'=>$request->sup_id,'customer_type'=>'supplier'])->first();
            if(!$supplier){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Supplier Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $add_amount=0;
            $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount!=0) ? $request->cash_amount : 0;
            $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount!=0) ? $request->cheque_amount : 0;
            $add_amount+= ($cash_amount+$cheque_amount);

            $lastLedger = $supplier->ledgers()->orderBy('id', 'desc')->first();
            $previousBalance=0.00;
            if($lastLedger){
                $previousBalance=$lastLedger->balance;
            }else{
                $previousBalance=$supplier->opening_balance;
            }
            $rem_blnc_amount=$previousBalance-$add_amount;

            DB::beginTransaction();
            $comp_debit_amt=0;
            $transactionData=['customer_id'=>$request->sup_id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>0.00,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null,'customer_type'=>'supplier','book_id'=>null,'balance'=>$rem_blnc_amount];
            
            if($add_amount>=1){
                $transactionData['cr_amount']=$add_amount;
                $transactionData['entry_type']='cr';
            }else{
                $transactionData['dr_amount']=abs($add_amount);
                $transactionData['entry_type']='dr';
            }

            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= abs($cheque_amount);
                $transactionData['bank_tax']= $request->bank_tax;
            }else if($request->input('payment_type') == 'cash'){
                $comp_debit_amt+=$cash_amount;
                $transactionData['cash_amount']= abs($cash_amount);
            }else if($request->input('payment_type') == 'online'){
                $transactionData['cash_amount']= abs($cash_amount);
                $transactionData['transection_id']= $request->transection_id;
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['bank_tax']= $request->bank_tax;
            }else{
                $comp_debit_amt+=$cash_amount;
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= abs($cheque_amount);
                $transactionData['cash_amount']= abs($cash_amount);
                $transactionData['bank_tax']= $request->bank_tax;
            }
            
            $res=$supplier->addTransaction($transactionData);

            if(!$res){
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Create Supplier Ledger.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            if($request->input('payment_type') == 'cash' || $request->input('payment_type')=='both'){
                //company ledger
                $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>0.00,'description'=>$request->description,'link_id'=>$res->id,'link_name'=>'supplier_ledger'];

                if($comp_debit_amt>=1){
                    $transactionDataComp['dr_amount']=$comp_debit_amt;
                    $transactionDataComp['entry_type']='dr';
                }else{
                    $transactionDataComp['cr_amount']=abs($comp_debit_amt);
                    $transactionDataComp['entry_type']='cr';
                }
    
                $res=$supplier->addCompanyTransaction($transactionDataComp);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
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
    // public function update(Request $request, $id)
    // {
    //     $rules = [
    //         'payment_type' => 'required|in:cash,cheque,both,online',
    //         'description' => 'nullable|string',
    //     ];        

    //     if ($request->input('payment_type') == 'cheque') {
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //         $rules['cheque_no']= 'required|string|max:100';
    //         $rules['cheque_date']= 'required|date';
    //         $rules['cheque_amount']= 'required|numeric|min:1';
    //         $rules['bank_tax']= 'required|numeric|min:0';            
    //     }else if($request->input('payment_type') == 'cash'){
    //         $rules['cash_amount']= 'required|numeric|min:1';
    //     }else if($request->input('payment_type') == 'online'){
    //         $rules['cash_amount']= ['required','numeric','min:1', 
    //         function ($attribute, $value, $fail) use ($request) {
    //             $bank = Bank::find($request->input('bank_id'));
    //             if ($bank && $value > $bank->balance) {
    //                 $fail('The transection amount cannot be greater than the bank balance.');
    //             }
    //         }];
    //         $rules['transection_id']= 'required|string|max:100';
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //         $rules['bank_tax']= 'required|numeric|min:0';
    //     }else{
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //         $rules['cheque_no']= 'required|string|max:100';
    //         $rules['cheque_date']= 'required|date';
    //         $rules['cheque_amount']= 'required|numeric|min:1';
    //         $rules['cash_amount']= 'required|numeric|min:1';
    //         $rules['bank_tax']= 'required|numeric|min:0';
    //     }

    //     $validator = Validator::make($request->all(), $rules);
        
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $validator->errors()->first(),
    //         ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
    //     }
        
    //     try {
    //         $payment_type=$request->input('payment_type');
    //         $supplier_ledger = CustomerLedger::where('customer_type','supplier')->where('id',$id)->where('description','!=','Opening Balance')->firstOrFail();
           
    //         $add_amount=0;
    //         $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
    //         $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
    //         $add_amount+= ($cash_amount+$cheque_amount);
    //         $lastLedger = CustomerLedger::where('customer_id', $supplier_ledger->customer_id)->where('id', '<', $id)->orderBy('id', 'desc')->first();
            
    //         $previousBalance=0;
    //         if($lastLedger){
    //             $previousBalance=$lastLedger->balance;
    //         }

    //         $rem_blnc_amount=$previousBalance-$add_amount;

    //         DB::beginTransaction();
    //         $comp_debit_amt=0;

    //         $transactionData=['id'=>$supplier_ledger->id,'model_name'=>'App\Models\CustomerLedger','bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$add_amount,
    //         'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
    //         'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null,'customer_type'=>'supplier','book_id'=>null,'entry_type'=>'cr','balance'=>$rem_blnc_amount];
           
    //         if ($request->input('payment_type') == 'cheque') {
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['cheque_no']= $request->cheque_no;
    //             $transactionData['cheque_date']= $request->cheque_date;
    //             $transactionData['cheque_amount']= $cheque_amount;
    //             $transactionData['bank_tax']= $request->bank_tax;
    //         }else if($request->input('payment_type') == 'cash'){
    //             $comp_debit_amt+=$cash_amount;
    //             $transactionData['cash_amount']= $cash_amount;
    //         }else if($request->input('payment_type') == 'online'){
    //             $transactionData['cash_amount']= $cash_amount;;
    //             $transactionData['transection_id']= $request->transection_id;
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['bank_tax']= $request->bank_tax;
    //         }else{
    //             $comp_debit_amt+=$cash_amount;
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['cheque_no']= $request->cheque_no;
    //             $transactionData['cheque_date']= $request->cheque_date;
    //             $transactionData['cheque_amount']= $cheque_amount;
    //             $transactionData['cash_amount']= $cash_amount;
    //             $transactionData['bank_tax']= $request->bank_tax;
    //         }
            
    //         $res=$supplier_ledger->updateTransaction($transactionData);
    //         if($res->original['status']!='success'){
    //             DB::rollBack();
    //             return response()->json($res->original, Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //         }

    //         //company ledger update
    //         $company_ledger=CompanyLedger::where('link_id',$id)->where('link_name','supplier_ledger')->first();
    //         if($company_ledger){
    //             if($request->payment_type=="cash" || $request->payment_type=="both"){
    //                 $transactionDataComp=['id'=>$company_ledger->id,'dr_amount'=>$comp_debit_amt,'cr_amount'=>0.00,'description'=>$request->description];
    //                 $supplier_ledger->updateCompanyTransaction($transactionDataComp);
    //             }else{
    //                 $supplier_ledger->deleteCompanyTransection($company_ledger->id);
    //             }
    //         }else{
    //             if($request->payment_type=="cash" || $request->payment_type=="both"){
    //                 $transactionDataComp=['dr_amount'=>$comp_debit_amt,'cr_amount'=>0.00,'description'=>$request->description,'entry_type'=>'dr','link_id'=>$supplier_ledger->id,'link_name'=>'supplier_ledger'];
    //                 $res=$supplier_ledger->addCompanyTransaction($transactionDataComp);
    //                 if(!$res){
    //                     DB::rollBack();
    //                     return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //                 }
    //             }
    //         }

    //         // Commit the transaction
    //         DB::commit();
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Ledger Updated Successfully.',
    //         ], Response::HTTP_CREATED); // 201 Created
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollBack();
    //         return response()->json(['status'=>'error', 'message' => 'Supplier Not Found.'], Response::HTTP_NOT_FOUND);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['status'=>'error','message' => 'Failed to Update Supplier. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     } 
    // }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $resource = CustomerLedger::where('customer_type','supplier')->where('id',$id)->where('description','!=','Opening Balance')->firstOrFail();
            $res=$resource->deleteTransection($id);
            $company_res=CompanyLedger::where('link_id',$id)->where('link_name','supplier_ledger')->first();
            $res=$resource->deleteCompanyTransection($company_res->id);
            return response()->json($res->original);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Supplier Ledger Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
