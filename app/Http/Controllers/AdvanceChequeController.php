<?php

namespace App\Http\Controllers;

use App\Models\AdvanceCheque;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;


class AdvanceChequeController extends Controller
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
                    $customer->cheques = $customer->advanceCheques()->whereBetween('created_at', [$startDate, $endDate])->get();
                    return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $customer]);
                }else{
                    $customer->cheques = $customer->advanceCheques()->get();
                    return response()->json(['data' => $customer]);
                }
            }else{
                return response()->json(['status'=>'error', 'message' => 'Party Not Found.'], Response::HTTP_NOT_FOUND);
            }
        }else{
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $party_cheques = AdvanceCheque::with(['customer:id,person_name'])->whereBetween('created_at', [$startDate, $endDate])->get();
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $party_cheques]);
            }else{
                $party_cheques =AdvanceCheque::with(['customer:id,person_name'])->get();
                return response()->json(['data' => $party_cheques]);
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
            'description' => 'nullable|string',
            'bank_id' => ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')],
            'cheque_no' => 'required|string|max:100',
            'cheque_date' => 'required|date',
            'cheque_amount' => 'required|numeric|min:1',
        ];        

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $party=Customer::where(['id'=>$request->party_id,'customer_type'=>'party'])->first();
            if(!$party){
                return response()->json(['status' => 'error','message' => 'Party Does Not Exist.',], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $cheque_amount= ($request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            DB::beginTransaction();
            
            $advance_cheque = AdvanceCheque::create([
                'customer_id' => $request->party_id,
                'description' => $request->description,
                'customer_type' => 'party',
                'bank_id' => $request->bank_id,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'cheque_amount' => $cheque_amount,
            ]);
            
            if(!$advance_cheque){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Create Advance Cheque.',], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }
            DB::commit();
            return response()->json(['status' => 'success','message' => 'Advance Cheque Added Successfully.',], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Create Advance Cheque. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
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
            $party_cheques = AdvanceCheque::with(['customer:id,person_name'])->findOrFail($id);
            return response()->json(['data' => $party_cheques]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Party Advance Cheque Not Found.'], Response::HTTP_NOT_FOUND);
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
            'party_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'description' => 'nullable|string',
            'bank_id' => ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')],
            'cheque_no' => 'required|string|max:100',
            'cheque_date' => 'required|date',
            'cheque_amount' => 'required|numeric|min:1',
        ];   

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $party=Customer::where(['id'=>$request->party_id,'customer_type'=>'party'])->first();
            if(!$party){
                return response()->json(['status' => 'error','message' => 'Party Does Not Exist.',], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $advance_cheque=AdvanceCheque::findOrFail($id);
            $cheque_amount= ($request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            DB::beginTransaction();
            
            $advance_cheque->update([
                'customer_id' => $request->party_id,
                'description' => $request->description,
                'bank_id' => $request->bank_id,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'cheque_amount' => $cheque_amount,
            ]);
            
            if(!$advance_cheque){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Update Cheque.',], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            DB::commit();
            return response()->json(['status' => 'success','message' => 'Cheque Updated Successfully.',], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Cheque Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message' => 'Failed to Update Cheque. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = AdvanceCheque::findOrFail($id);
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Cheque Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Cheque Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }

    public function changeStatus($id,$value){
        try {
            $advance_cheque=AdvanceCheque::findOrFail($id);
            if($value==1){
                $advance_cheque->update([
                    'is_deferred' =>1,
                ]);
                $message='Cheque Deferred Successfully.';
            }else{
                $party=Customer::where(['id'=>$advance_cheque->customer_id,'customer_type'=>'party'])->first();
                if(!$party){
                    return response()->json(['status' => 'error','message' => 'Party Does Not Exist.',], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
                }

                $lastLedger = $party->ledgers()->orderBy('id', 'desc')->first();
                $previousBalance=0.00;
                if($lastLedger){
                    $previousBalance=$lastLedger->balance;
                }else{
                    $previousBalance=$party->opening_balance;
                }
                $rem_blnc_amount=$previousBalance-$advance_cheque->cheque_amount;

                $transactionData=['customer_id'=>$advance_cheque->customer_id,'bank_id'=>$advance_cheque->bank_id,'description'=>$advance_cheque->description,'dr_amount'=>0.00,'cr_amount'=>$advance_cheque->cheque_amount,
                'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>'cheque','cheque_amount'=>$advance_cheque->cheque_amount,
                'cheque_no'=>$advance_cheque->cheque_no,'cheque_date'=>$advance_cheque->cheque_date,'customer_type'=>'party','book_id'=>null,'entry_type'=>'cr','balance'=>$rem_blnc_amount];
                
                $res=$party->addTransaction($transactionData);
    
                if(!$res){
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to Add Party Ledger.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
                $advance_cheque->delete();
                $message='Cheque Added in Ledger Successfully.';
            }
            DB::commit();
            return response()->json(['status' => 'success','message' => $message,], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Cheque Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message' => 'Failed to Update Cheque. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }   
}
