<?php

namespace App\Http\Controllers;

use App\Models\CompanyProductStock;
use App\Models\Product;
use App\Rules\CheckBankBalance;
use App\Rules\ExistsNotSoftDeleted;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CompanyProductStockController extends Controller
{
    //
    public function store(Request $request)
    {
        $rules = [
            'product_id' => ['required', 'exists:products,id', new ExistsNotSoftDeleted('products')],
            'payment_type' => 'required|in:cash,cheque,both,online',
            'description' => 'nullable|string',
        ];
        $is_bank_entry=false;
        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            if($request->cash_amount>=1){
                $rules['cheque_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cheque_amount']= ['required','numeric','min:1'];
            }
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'online'){
            $rules['transection_id']= 'required|string|max:100';
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            if($request->cash_amount>=1){
                $rules['cash_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cash_amount']= ['required','numeric','min:1'];
            }
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            if($request->cash_amount>=1){
                $rules['cheque_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cheque_amount']= ['required','numeric','min:1'];
            }
            $rules['cash_amount']= 'required|numeric|min:1';
            $is_bank_entry=true;
            $check_bank_arr['bank_id']=$request->input('bank_id');
            $check_bank_arr['given_balance']=$request->input('cheque_amount');
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first()],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        //check bank balance
        if($is_bank_entry){
            if (!$this->checkBankBalance($check_bank_arr['bank_id'], $check_bank_arr['given_balance'])) {
                return response()->json(['status' => 'error','message' => 'Insufficient bank balance.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        
        try {
            $payment_type=$request->input('payment_type');
           
            // Start a transaction
            DB::beginTransaction();

            $data_arr=[
                'product_id' => $request->input('product_id'),
                'payment_type' => $request->input('payment_type'),
                'description' => $request->input('description'),
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'bank_id' => $request->bank_id,
                'transection_id' => $payment_type=='online'?$request->transection_id:null,
                'entry_type' => 'expense',
                'linkable_id' => $request->input('product_id'),
                'linkable_type' => Product::class,
            ];
            
            $data_arr['cash_amount']= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $data_arr['cheque_amount']= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            $data_arr['total_amount']=$data_arr['cash_amount']+$data_arr['cheque_amount'];
            
            $stock_balance = CompanyProductStock::where(['product_id'=> $request->product_id])->latest()->first();
            $old_balance=$stock_balance?$stock_balance->balance:0;

            $data_arr['balance']=$old_balance+$data_arr['total_amount'];
            $companyProductStock = CompanyProductStock::create($data_arr);
            
            $data_arr['customer_type']='party'; 
            $data_arr['entry_type']='cr';
            $companyProductStock->reCalculateBankBalance($data_arr);

            if($request->input('payment_type') == 'cash' || $request->input('payment_type')=='both'){
                //company ledger
                $transactionDataComp=['dr_amount'=>$data_arr['cash_amount'],'cr_amount'=>0.00,'description'=>$request->description,'entry_type'=>'dr','link_id'=>$companyProductStock->id,'link_name'=>'product_expense'];
                $res=$companyProductStock->addCompanyTransaction($transactionDataComp);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Product Expense Added Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Product Expense. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }
}
