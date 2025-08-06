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
            'transection_id'=>$request->transection_id, 'customer_type'=>'self','book_id'=>null,'balance'=>$new_blnc_amount,'entry_type'=>'cr'];
            
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

    public function bankToBankTransfer(Request $request)
    {
        $rules = [
            'from_bank_id' => ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')],
            'to_bank_id' => ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')],
            'amount'  => ['required','numeric','not_in:0', new CheckBankBalance($request->input('from_bank_id'))],
            'description' => 'nullable|string',
            'transection_id' => 'nullable|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],422);// 422 Unprocessable Entity
        }
        
        if($request->from_bank_id == $request->to_bank_id){
            return response()->json(['status' => 'error','message' => 'From Bank and To Bank cannot be same.'],422);
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

            $amount = $request->amount;
            $description = $request->description;
            $transactionId = $request->transection_id;
        
            // Transfer between banks
            foreach ([
                ['bank_id' => $request->from_bank_id, 'type' => 'dr_amount', 'entry_type' => 'dr', 'sign' => -1],
                ['bank_id' => $request->to_bank_id, 'type' => 'cr_amount', 'entry_type' => 'cr', 'sign' => 1],
            ] as $entry) {
                $bank = Bank::find($entry['bank_id']);
                $bank->balance += $entry['sign'] * $amount;
                $bank->save();
        
                $bank->addBankTransection([
                    'bank_id'        => $entry['bank_id'],
                    'description'    => $description,
                    'payment_type'   => 'online',
                    'entry_type'     => $entry['entry_type'],
                    'online_amount'  => $amount,
                    $entry['type']   => $amount,
                    'transection_id' => $transactionId,
                    'linkable_id'    => $party->id,
                    'linkable_type'  => Customer::class,
                    'link_name'      => 'bank_to_bank_self',
                    'balance'        => $bank->balance,
                ]);
            }

            DB::commit();
            return response()->json(['status' => 'success','message' => 'Payment Transferred Successfully from Bank to Bank.',],); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Self Payment.' . $e->getMessage(),], 500); 
        }
    }

}
