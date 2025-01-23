<?php

namespace App\Http\Controllers;

use App\Models\CompanyLedger;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Rules\CheckBankBalance;
use App\Rules\CheckCashBalance;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class PartyLedgerController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        if($request->has('party_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','party')->where('id',$request->party_id)->first();
            if($customer){
                if($request->has('start_date') && $request->has('end_date')){
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                    $customer->ledgers= $customer->load(['ledgers' => function($query) use ($startDate, $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate])->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers= $customer->load(['ledgers' => function($query) {
                        $query->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Party Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $party_ledger = CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','party')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $party_ledger]);
            }else{
                $party_ledger =CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','party')->get();
                return response()->json(['data' => $party_ledger]);
            }
        }
    }

    public function receivedPartyAmount(Request $request)
    {   
        if($request->has('party_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','party')->where('id',$request->party_id)->first();
            if($customer){
                if($request->has('start_date') && $request->has('end_date')){
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                    $customer->ledgers= $customer->load(['ledgers' => function($query) use ($startDate, $endDate) {
                        $query->where('entry_type', 'cr')
                              ->whereBetween('created_at', [$startDate, $endDate])
                              ->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers= $customer->load(['ledgers' => function($query) {
                        $query->where('entry_type', 'cr')
                              ->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Pparty Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $party_ledger = CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','party')->where('entry_type','cr')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $party_ledger]);
            }else{
                $party_ledger =CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('entry_type','cr')->where('customer_type','party')->get();
                return response()->json(['data' => $party_ledger]);
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
            'party_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'payment_type' => 'required|in:cash,cheque,both,online',
            'description' => 'nullable|string',
        ];   
        
        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            if($request->cheque_amount>0){ //+
                $rules['cheque_amount']= ['required','numeric','not_in:0', new CheckBankBalance($request->input('bank_id'))];
            }else{ //-
                // return please add in adv cheque
                $rules['cheque_amount']= ['required','numeric','not_in:0'];
            }
        }else if($request->input('payment_type') == 'cash'){
            if($request->cash_amount>0){ //+
                $rules['cash_amount']= ['required','numeric','not_in:0',new CheckCashBalance()];
            }else{ //-
                $rules['cash_amount']= ['required','numeric','not_in:0'];
            }
        }else if($request->input('payment_type') == 'online'){
            $rules['transection_id']= 'required|string|max:100';
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            if($request->cash_amount>0){//+
                $rules['cash_amount']= ['required','numeric','not_in:0', new CheckBankBalance($request->input('bank_id'))];
            }else{//-
                $rules['cash_amount']= ['required','numeric','not_in:0'];
            }
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            // $rules['cash_amount']= 'required|numeric|not_in:0';
            if($request->cash_amount>0){//+
                $rules['cash_amount']= ['required','numeric','not_in:0',new CheckCashBalance()];
                $rules['cheque_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{//-
                $rules['cash_amount']= 'required|numeric|not_in:0';
                $rules['cheque_amount']= ['required','numeric','not_in:0'];
            }
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first()],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $payment_type=$request->input('payment_type');
            $party=Customer::where(['id'=>$request->party_id,'customer_type'=>'party'])->first();
            if(!$party){
                return response()->json(['status' => 'error','message' => 'Pparty Does Not Exist.'], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $add_amount=0;
            $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount!=0) ? $request->cash_amount : 0;
            $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount!=0) ? $request->cheque_amount : 0;
            $add_amount+= ($cash_amount+$cheque_amount);

            $lastLedger = $party->ledgers()->orderBy('id', 'desc')->first();
            $previousBalance=0.00;
            if($lastLedger){
                $previousBalance=$lastLedger->balance;
            }else{
                $previousBalance=$party->opening_balance;
            }
           
            DB::beginTransaction();
            $comp_credit_amt=0;
            $new_blnc_amount=$previousBalance+$add_amount;

            $transactionData=['customer_id'=>$request->party_id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>0.00,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,'cheque_no'=>null,'cheque_date'=>null,
            'transection_id'=>null, 'customer_type'=>'party','book_id'=>null,'balance'=>$new_blnc_amount];

            if($add_amount>0){//+  we will pay
                $transactionData['cr_amount']=$add_amount;
                $transactionData['entry_type']='cr';
            }else{//- we will receive
                $transactionData['dr_amount']=abs($add_amount);
                $transactionData['entry_type']='dr';
            }

            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= abs($cheque_amount);
            }else if($request->input('payment_type') == 'cash'){
                $comp_credit_amt-=$cash_amount;
                $transactionData['cash_amount']= abs($cash_amount);
            }else if($request->input('payment_type') == 'online'){
                $transactionData['cash_amount']= abs($cash_amount);
                $transactionData['transection_id']= $request->transection_id;
                $transactionData['bank_id'] = $request->bank_id;
                
            }else{
                $comp_credit_amt-=$cash_amount;
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= abs($cheque_amount);
                $transactionData['cash_amount']= abs($cash_amount);
            }
            
            $res=$party->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Create Party Ledger.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            if($request->input('payment_type') == 'cash' || $request->input('payment_type')=='both'){
                //company ledger
                $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>0.00,'description'=>$request->description,'link_id'=>$res->id,'link_name'=>'party_ledger'];
                if($comp_credit_amt>0){
                    $transactionDataComp['entry_type']='cr';
                    $transactionDataComp['cr_amount']=$comp_credit_amt;
                }else{
                    $transactionDataComp['entry_type']='dr';
                    $transactionDataComp['dr_amount']=abs($comp_credit_amt);
                }
                
                $res=$party->addCompanyTransaction($transactionDataComp);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
            }

            DB::commit();
            $last_4_ledger=$party->ledgers()->latest()->take(4)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Party Ledger Created Successfully.',
                'party'  => $party,
                'ledger' => $last_4_ledger
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Party Ledger. ' . $e->getMessage(),
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
            $party_ledger = CustomerLedger::with(['customer:id,person_name'])->findOrFail($id);
            return response()->json(['data' => $party_ledger]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Party Ledger Not Found.'], Response::HTTP_NOT_FOUND);
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
    //     }else if($request->input('payment_type') == 'cash'){
    //         $rules['cash_amount']= 'required|numeric|min:1';
    //     }else if($request->input('payment_type') == 'online'){
    //         $rules['cash_amount']= 'required|numeric|min:1';
    //         $rules['transection_id']= 'required|string|max:100';
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //     }else{
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //         $rules['cheque_no']= 'required|string|max:100';
    //         $rules['cheque_date']= 'required|date';
    //         $rules['cheque_amount']= 'required|numeric|min:1';
    //         $rules['cash_amount']= 'required|numeric|min:1';
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
    //         $buyer_ledger = CustomerLedger::where('customer_type','buyer')->where('id',$id)->where('description','!=','Opening Balance')->firstOrFail();
           
    //         $add_amount=0;
    //         $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
    //         $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
    //         $add_amount+= ($cash_amount+$cheque_amount);
    //         $lastLedger = CustomerLedger::where('customer_id', $buyer_ledger->customer_id)->where('id', '<', $id)->orderBy('id', 'desc')->first();
            
    //         $previousBalance=0;
    //         if($lastLedger){
    //             $previousBalance=$lastLedger->balance;
    //         }

    //         $rem_blnc_amount=$previousBalance-$add_amount;

    //         DB::beginTransaction();
    //         $comp_credit_amt=0;
            
    //         $transactionData=['id'=>$buyer_ledger->id,'model_name'=>'App\Models\CustomerLedger','bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$add_amount,
    //         'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
    //         'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null,'customer_type'=>'buyer','book_id'=>null,'entry_type'=>'cr','balance'=>$rem_blnc_amount];
           
    //         if ($request->input('payment_type') == 'cheque') {
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['cheque_no']= $request->cheque_no;
    //             $transactionData['cheque_date']= $request->cheque_date;
    //             $transactionData['cheque_amount']= $cheque_amount;
    //         }else if($request->input('payment_type') == 'cash'){
    //             $comp_credit_amt+=$cash_amount;
    //             $transactionData['cash_amount']= $cash_amount;
    //         }else if($request->input('payment_type') == 'online'){
    //             $transactionData['cash_amount'] = $cash_amount;
    //             $transactionData['transection_id'] = $request->transection_id;
    //             $transactionData['bank_id'] = $request->bank_id;
    //         }else{
    //             $comp_credit_amt+=$cash_amount;
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['cheque_no']= $request->cheque_no;
    //             $transactionData['cheque_date']= $request->cheque_date;
    //             $transactionData['cheque_amount']= $cheque_amount;
    //             $transactionData['cash_amount']= $cash_amount;
    //         }
            
    //         $res=$buyer_ledger->updateTransaction($transactionData);
    //         if($res->original['status']!='success'){
    //             DB::rollBack();
    //             return response()->json($res->original, Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //         }

    //         //company ledger update
    //         $company_ledger=CompanyLedger::where('link_id',$id)->where('link_name','buyer_ledger')->first();
    //         if($company_ledger){
    //             if($request->payment_type=="cash" || $request->payment_type=="both"){
    //                 $transactionDataComp=['id'=>$company_ledger->id,'dr_amount'=>0.00,'cr_amount'=>$comp_credit_amt,'description'=>$request->description];
    //                 $buyer_ledger->updateCompanyTransaction($transactionDataComp);
    //             }else{
    //                 $buyer_ledger->deleteCompanyTransection($company_ledger->id);
    //             }
    //         }else{
    //             if($request->payment_type=="cash" || $request->payment_type=="both"){
    //                 $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>$comp_credit_amt,'description'=>$request->description,'entry_type'=>'cr','link_id'=>$buyer_ledger->id,'link_name'=>'buyer_ledger'];
    //                 $res=$buyer_ledger->addCompanyTransaction($transactionDataComp);
    //                 if(!$res){
    //                     DB::rollBack();
    //                     return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //                 }
    //             }
    //         }

    //         if($res->original['status']=='success'){
    //             DB::commit();
    //             return response()->json($res->original, Response::HTTP_OK); // 200 OK
    //         }else{
    //             DB::rollBack();
    //             return response()->json($res->original, Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //         }
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollBack();
    //         return response()->json(['status'=>'error', 'message' => 'Buyer Not Found.'], Response::HTTP_NOT_FOUND);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['status'=>'error','message' => 'Failed to Update Buyer. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = CustomerLedger::where('customer_type','party')->where('id',$id)->where('description','!=','Opening Balance')->firstOrFail();
            $res=$resource->deleteTransection($id);
            return response()->json($res->original);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Party Ledger Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
