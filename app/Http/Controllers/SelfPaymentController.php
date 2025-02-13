<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanyLedger;
use App\Models\Customer;
use App\Rules\CheckBankBalance;
use App\Rules\ExistsNotSoftDeleted;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SelfPaymentController extends Controller
{
    //    
    public function bankToCashTransfer(Request $request)
    {
        $rules = [
            'bank_id' => ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')],
            'amount'  => ['required','numeric','not_in:0', new CheckBankBalance($request->input('bank_id'))],
            'description' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],422);// 422 Unprocessable Entity
        }
        
        try {
            DB::beginTransaction();
            
            $party = Customer::firstOrCreate(
                ['customer_type' => 'self'], // Search criteria
                [
                    'person_name' => 'Gulam Bari', // Required default values
                    'firm_name' => 'Gulam Bari',
                    'opening_balance' => 0,
                    'current_balance' => 0
                ]
            );
            
            $lastLedger = $party->ledgers()->orderBy('id', 'desc')->first();
            $previousBalance=0.00;
            if($lastLedger){
                $previousBalance=$lastLedger->balance;
            }else{
                $previousBalance=$party->opening_balance;
            }
            $new_blnc_amount=$previousBalance+$request->amount;

            $transactionData=['customer_id'=>$party->id,'bank_id'=>$request->bank_id,'description'=>$request->description,'dr_amount'=>0.00,'cr_amount'=>$request->amount,
            'adv_amount'=>0.00,'cash_amount'=>$request->amount,'payment_type'=>'online','cheque_amount'=>0.00,'cheque_no'=>null,'cheque_date'=>null,
            'transection_id'=>null, 'customer_type'=>'self','book_id'=>null,'balance'=>$new_blnc_amount];
            
            $res=$party->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Add Self Payment.'], 500);
            }

            //update company cash ledger
            $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>$request->amount,'description'=>$request->description,'entry_type'=>'cr','link_id'=>null,'link_name'=>'bank_to_cash_self'];
            $company_ledger =new CompanyLedger();
            $res=$company_ledger->addCompanyTransaction($transactionDataComp);
            
            if(!$res){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'],500); // 500 Internal Server Error
            }

            DB::commit();
            return response()->json(['status' => 'success','message' => 'Payment Transferred Successfully from Bank to Cash.',],); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Self Payment.' . $e->getMessage(),], 500); 
        }
    }
}
