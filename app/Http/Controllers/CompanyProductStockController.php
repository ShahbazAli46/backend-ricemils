<?php

namespace App\Http\Controllers;

use App\Models\BankLedger;
use App\Models\CompanyLedger;
use App\Models\CompanyProductStock;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\User;
use App\Rules\CheckBankBalance;
use App\Rules\ExistsNotSoftDeleted;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CompanyProductStockController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('start_date') && $request->has('end_date')){
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
            $receive_amt= CompanyProductStock::with(['linkable','product','user:'.User::SELECT_BASIC_STRING])->where('entry_type','expense')->where('expense_entry_type','cr')->whereBetween('created_at', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $receive_amt]);
        }else{
            $receive_amt= CompanyProductStock::with(['linkable','product','user:'.User::SELECT_BASIC_STRING])->where('entry_type','expense')->where('expense_entry_type','cr')->get();
            return response()->json(['data' => $receive_amt]);
        }
        return response()->json(['data' => $receive_amt]); 
    }


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
            if($request->cheque_amount>=1){
                $rules['cheque_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cheque_amount']= ['required','numeric','not_in:0'];
            }
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|not_in:0';
        }else if($request->input('payment_type') == 'online'){
            $rules['transection_id']= 'required|string|max:100';
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            if($request->cash_amount>=1){
                $rules['cash_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
            }else{
                $rules['cash_amount']= ['required','numeric','not_in:0'];
            }
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            if($request->cheque_amount>=1){
                $rules['cheque_amount']= ['required','numeric', new CheckBankBalance($request->input('bank_id'))];
                $is_bank_entry=true;
            }else{
                $rules['cheque_amount']= ['required','numeric','not_in:0'];
            }
            $rules['cash_amount']= 'required|numeric|not_in:0';
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

            $stock_balance = CompanyProductStock::where(['product_id'=> $request->product_id])->latest()->first();
            $old_balance=$stock_balance?$stock_balance->balance:0;
            
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
                'user_id' => auth()->id(),
            ];

            $data_arr['cash_amount']= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') ) ? $request->cash_amount : 0;
            $data_arr['cheque_amount']= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') ) ? $request->cheque_amount : 0;
            $data_arr['total_amount']=$data_arr['cash_amount']+$data_arr['cheque_amount'];
            
            $original_total_amt=$data_arr['total_amount'];
            $data_arr['total_amount']=abs($data_arr['total_amount']);
            $data_arr['cash_amount']=abs($data_arr['cash_amount']);
            $data_arr['cheque_amount']=abs($data_arr['cheque_amount']);

            $exp_category = ExpenseCategory::firstOrCreate(['expense_category' => 'Product Expense']);
            $data_arr['expense_category_id'] = $exp_category->id;
            $message="Product Expense Added Successfully.";
            if($original_total_amt>0){
                $expense = Expense::create($data_arr);
                $data_arr['balance']=$old_balance+$data_arr['total_amount'];
                $entry_type_com='cr';
                $data_arr['expense_entry_type']='dr';
            }else{
                $data_arr['balance']=$old_balance-$data_arr['total_amount'];
                $entry_type_com='dr';
                $data_arr['expense_entry_type']='cr';
                $message="Payment Received Successfully.";
            }

            $companyProductStock = CompanyProductStock::create($data_arr);
            $data_arr['customer_type']='party'; 
            $data_arr['entry_type']=$entry_type_com;
            
            $bankLedgerEntry=$companyProductStock->reCalculateBankBalance($data_arr);
            if(isset($bankLedgerEntry)){
                $companyProductStock->update(['linkable_id' => $bankLedgerEntry->id,'linkable_type' => BankLedger::class,]);
            }

            if($request->input('payment_type') == 'cash' || $request->input('payment_type')=='both'){
                //company ledger
                if($original_total_amt>0){
                    $transactionDataComp=['dr_amount'=>$data_arr['cash_amount'],'cr_amount'=>0.00,'description'=>$request->description,'entry_type'=>'dr','link_id'=>$companyProductStock->id,'link_name'=>'product_expense'];
                }else{
                    $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>$data_arr['cash_amount'],'description'=>$request->description,'entry_type'=>'cr','link_id'=>$companyProductStock->id,'link_name'=>'product_expense'];
                }
                $res=$companyProductStock->addCompanyTransaction($transactionDataComp);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
                $companyProductStock->update(['linkable_id' => $res->id,'linkable_type' => CompanyLedger::class]);
            }

            DB::commit();
            return response()->json(['status' => 'success','message' => $message], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Product Expense. ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    public function show($id)
    {
        try {
            $com_product_detail=CompanyProductStock::with(['linkable','product','user:'.User::SELECT_BASIC_STRING])->where('id',$id)->firstOrFail();
            return response()->json(['data' => $com_product_detail]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Product Stock Not Found.'], Response::HTTP_NOT_FOUND);
        }
    }
}
