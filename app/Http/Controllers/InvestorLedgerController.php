<?php

namespace App\Http\Controllers;
use App\Models\CompanyLedger;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;

use Illuminate\Support\Facades\DB;

class InvestorLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        if($request->has('investor_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','investor')->where('id',$request->investor_id)->first();
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
                return response()->json(['status'=>'error', 'message' => 'Investor Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $investor_ledger = CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','investor')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $investor_ledger]);
            }else{
                $investor_ledger =CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','investor')->get();
                return response()->json(['data' => $investor_ledger]);
            }
        }
    }

    public function receivedInvestorAmount(Request $request)
    {   
        if($request->has('investor_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','investor')->where('id',$request->investor_id)->first();
            if($customer){
                if($request->has('start_date') && $request->has('end_date')){
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                    $customer->ledgers= $customer->load(['ledgers' => function($query) use ($startDate, $endDate) {
                        $query->where('entry_type', 'dr')
                              ->whereBetween('created_at', [$startDate, $endDate])
                              ->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers= $customer->load(['ledgers' => function($query) {
                        $query->where('entry_type', 'dr')
                              ->with('bank:id,bank_name'); // Include bank details
                    }]);
                    // $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Investor Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $investor_ledger = CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('customer_type','investor')->where('entry_type','dr')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $investor_ledger]);
            }else{
                $investor_ledger =CustomerLedger::with(['customer:id,person_name','bank:id,bank_name'])->where('entry_type','dr')->where('customer_type','investor')->get();
                return response()->json(['data' => $investor_ledger]);
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
            'investor_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'payment_type' => 'required|in:cash,cheque,both,online',
            'description' => 'nullable|string',
        ];        

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|not_in:0';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|not_in:0';
        }else if($request->input('payment_type') == 'online'){
            $rules['cash_amount']= 'required|numeric|not_in:0';
            $rules['transection_id']= 'required|string|max:100';
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|not_in:0';
            $rules['cash_amount']= 'required|numeric|not_in:0';
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
            $investor=Customer::where(['id'=>$request->investor_id,'customer_type'=>'investor'])->first();
            if(!$investor){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Investor Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $add_amount=0;
            $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount!=0) ? $request->cash_amount : 0;
            $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount!=0) ? $request->cheque_amount : 0;
            $add_amount+= ($cash_amount+$cheque_amount);

            $lastLedger = $investor->ledgers()->orderBy('id', 'desc')->first();
            $previousBalance=0.00;
            if($lastLedger){
                $previousBalance=$lastLedger->balance;
            }else{
                $previousBalance=$investor->opening_balance;
            }
            $rem_blnc_amount=$previousBalance+$add_amount;

            DB::beginTransaction();
            $comp_credit_amt=0;
            $transactionData=['customer_id'=>$request->investor_id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>0.00,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null, 'customer_type'=>'investor','book_id'=>null,'balance'=>$rem_blnc_amount];

            if($add_amount>=1){
                $transactionData['dr_amount']=$add_amount;
                $transactionData['entry_type']='dr';
            }else{
                $transactionData['cr_amount']=abs($add_amount);
                $transactionData['entry_type']='cr';
            }

            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= abs($cheque_amount);
            }else if($request->input('payment_type') == 'cash'){
                $comp_credit_amt+=$cash_amount;
                $transactionData['cash_amount']= abs($cash_amount);
            }else if($request->input('payment_type') == 'online'){
                $transactionData['cash_amount']= abs($cash_amount);
                $transactionData['transection_id']= $request->transection_id;
                $transactionData['bank_id'] = $request->bank_id;
            }else{
                $comp_credit_amt+=$cash_amount;
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= abs($cheque_amount);
                $transactionData['cash_amount']= abs($cash_amount);
            }
            
            $res=$investor->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Create Investor Ledger.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            if($request->input('payment_type') == 'cash' || $request->input('payment_type')=='both'){
                //company ledger
                $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>0.00,'description'=>$request->description,'link_id'=>$res->id,'link_name'=>'investor_ledger'];
                if($comp_credit_amt>=1){
                    $transactionDataComp['entry_type']='cr';
                    $transactionDataComp['cr_amount']=$comp_credit_amt;
                }else{
                    $transactionDataComp['entry_type']='dr';
                    $transactionDataComp['dr_amount']=abs($comp_credit_amt);
                }
                
                $res=$investor->addCompanyTransaction($transactionDataComp);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
            }

            DB::commit();
            $last_4_ledger=$investor->ledgers()->latest()->take(4)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Investor Ledger Created Successfully.',
                'investor'  => $investor,
                'ledger' => $last_4_ledger
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Investor Ledger. ' . $e->getMessage(),
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
            $investor_ledger = CustomerLedger::with(['customer:id,person_name'])->findOrFail($id);
            return response()->json(['data' => $investor_ledger]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Investor Ledger Not Found.'], Response::HTTP_NOT_FOUND);
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
