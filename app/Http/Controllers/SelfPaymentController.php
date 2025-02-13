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
                        
            $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>$request->amount,'description'=>$request->description,'entry_type'=>'cr','link_id'=>null,'link_name'=>'bank_to_cash_self'];
            $company_ledger =new CompanyLedger();
            $res=$company_ledger->addCompanyTransaction($transactionDataComp);
            
            if(!$res){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'],500); // 500 Internal Server Error
            }

            //update in bank
            $bank=Bank::find($request->bank_id);
            $bank->balance=$bank->balance-$request->amount;
            $bank->update();

            DB::commit();
            return response()->json(['status' => 'success','message' => 'Payment Transferred Successfully from Bank to Cash.',],); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Ledger. ' . $e->getMessage(),], 500); 
        }
    }
}
