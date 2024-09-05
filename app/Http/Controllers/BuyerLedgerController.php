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

class BuyerLedgerController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        if($request->has('buyer_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','buyer')->where('id',$request->buyer_id)->first();
            if($customer){
                if($request->has('start_date') && $request->has('end_date')){
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                    $customer->ledgers = $customer->ledgers()->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers = $customer->ledgers()->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Buyer Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $buyer_ledger = CustomerLedger::with(['customer:id,person_name'])->where('customer_type','buyer')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $buyer_ledger]);
            }else{
                $buyer_ledger =CustomerLedger::with(['customer:id,person_name'])->where('customer_type','buyer')->get();
                return response()->json(['data' => $buyer_ledger]);
            }
        }
    }

    public function receivedBuyerAmount(Request $request)
    {   
        if($request->has('buyer_id')){
            $customer=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','buyer')->where('id',$request->buyer_id)->first();
            if($customer){
                if($request->has('start_date') && $request->has('end_date')){
                    $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                    $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->ledgers = $customer->ledgers()->where('entry_type','cr')->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Buyer Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $buyer_ledger = CustomerLedger::with(['customer:id,person_name'])->where('customer_type','buyer')->where('entry_type','cr')->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $buyer_ledger]);
            }else{
                $buyer_ledger =CustomerLedger::with(['customer:id,person_name'])->where('entry_type','cr')->where('customer_type','buyer')->get();
                return response()->json(['data' => $buyer_ledger]);
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
            'buyer_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'payment_type' => 'required|in:cash,cheque,both,online',
            'description' => 'nullable|string',
        ];        

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'online'){
            $rules['cash_amount']= 'required|numeric|min:1';
            $rules['transection_id']= 'required|string|max:100';
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
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
            $payment_type=$request->input('payment_type');
            $buyer=Customer::where(['id'=>$request->buyer_id,'customer_type'=>'buyer'])->first();
            if(!$buyer){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Buyer Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $add_amount=0;
            $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            $add_amount+= ($cash_amount+$cheque_amount);

            $lastLedger = $buyer->ledgers()->orderBy('id', 'desc')->first();
            $previousBalance=0.00;
            if($lastLedger){
                $previousBalance=$lastLedger->balance;
            }else{
                $previousBalance=$buyer->opening_balance;
            }
            $rem_blnc_amount=$previousBalance-$add_amount;

            DB::beginTransaction();

            $transactionData=['customer_id'=>$request->buyer_id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$add_amount,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null, 'customer_type'=>'buyer','book_id'=>null,'entry_type'=>'cr','balance'=>$rem_blnc_amount];
            
            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
            }else if($request->input('payment_type') == 'cash'){
                $transactionData['cash_amount']= $cash_amount;
            }else if($request->input('payment_type') == 'online'){
                $transactionData['cash_amount']= $cash_amount;;
                $transactionData['transection_id']= $request->transection_id;
                $transactionData['bank_id'] = $request->bank_id;
            }else{
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
                $transactionData['cash_amount']= $cash_amount;
            }
            
            $res=$buyer->addTransaction($transactionData);

            if(!$res){
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Create Buyer Ledger.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            DB::commit();
            $last_4_ledger=$buyer->ledgers()->latest()->take(4)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Buyer Ledger Created Successfully.',
                'buyer'  => $buyer,
                'ledger' => $last_4_ledger
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Buyer Ledger. ' . $e->getMessage(),
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
            $buyer_ledger = CustomerLedger::with(['customer:id,person_name'])->findOrFail($id);
            return response()->json(['data' => $buyer_ledger]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Buyer Ledger Not Found.'], Response::HTTP_NOT_FOUND);
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
            'payment_type' => 'required|in:cash,cheque,both,online',
            'description' => 'nullable|string',
        ];        

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'online'){
            $rules['cash_amount']= 'required|numeric|min:1';
            $rules['transection_id']= 'required|string|max:100';
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
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
            $payment_type=$request->input('payment_type');
            $buyer_ledger = CustomerLedger::where('customer_type','buyer')->where('id',$id)->where('description','!=','Opening Balance')->firstOrFail();
           
            $add_amount=0;
            $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            $add_amount+= ($cash_amount+$cheque_amount);
            $lastLedger = CustomerLedger::where('customer_id', $buyer_ledger->customer_id)->where('id', '<', $id)->orderBy('id', 'desc')->first();
            
            $previousBalance=0;
            if($lastLedger){
                $previousBalance=$lastLedger->balance;
            }

            $rem_blnc_amount=$previousBalance-$add_amount;

            DB::beginTransaction();
            
            $transactionData=['id'=>$buyer_ledger->id,'model_name'=>'App\Models\CustomerLedger','bank_id'=>null,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$add_amount,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null,'customer_type'=>'buyer','book_id'=>null,'entry_type'=>'cr','balance'=>$rem_blnc_amount];
           
            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
            }else if($request->input('payment_type') == 'cash'){
                $transactionData['cash_amount']= $cash_amount;
            }else if($request->input('payment_type') == 'online'){
                $transactionData['cash_amount'] = $cash_amount;
                $transactionData['transection_id'] = $request->transection_id;
                $transactionData['bank_id'] = $request->bank_id;
            }else{
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
                $transactionData['cash_amount']= $cash_amount;
            }
            
            $res=$buyer_ledger->updateTransaction($transactionData);

            if($res->original['status']=='success'){
                DB::commit();
                return response()->json($res->original, Response::HTTP_OK); // 200 OK
            }else{
                DB::rollBack();
                return response()->json($res->original, Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Buyer Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message' => 'Failed to Update Buyer. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = CustomerLedger::where('customer_type','buyer')->where('id',$id)->where('description','!=','Opening Balance')->firstOrFail();
            $res=$resource->deleteTransection($id);
            return response()->json($res->original);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Buyer Ledger Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
