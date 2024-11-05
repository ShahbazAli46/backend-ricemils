<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanyLedger;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('date')){
            $date = \Carbon\Carbon::parse($request->input('date'))->startOfDay();
            $openingBalanceRecord = CompanyLedger::where('created_at', '<', $date)->orderBy('created_at', 'desc')->first();
            if($openingBalanceRecord){
                $todayOpeningBalance = CompanyLedger::whereDate('created_at', $date)->where('link_name','opening_balance')->sum('balance');
                $openingBalance = $openingBalanceRecord->balance+$todayOpeningBalance;
            }else{
                $openingBalance = CompanyLedger::whereDate('created_at', $date)->where('link_name','opening_balance')->sum('balance');
            }

            $inflowSumCom = CompanyLedger::whereDate('created_at', $date)->where('link_name','buyer_ledger')->sum('cr_amount');
            $outflowSumCom = CompanyLedger::whereDate('created_at', $date)->sum('dr_amount');

            //get cheque and online outflow
            $cheque_amount_out = CustomerLedger::whereDate('created_at', $date)->where('customer_type','supplier')->whereIn('entry_type',['dr','dr&cr'])->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            $cash_amount_out = CustomerLedger::whereDate('created_at', $date)->where('customer_type','supplier')->whereIn('entry_type',['dr','dr&cr'])->where('payment_type', 'online')->sum('cash_amount');
            
            //get cheque and online expense outflow
            $cheque_amount_expense_out = Expense::whereDate('created_at', $date)->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            $cash_amount_expense_out = Expense::whereDate('created_at', $date)->where('payment_type', 'online')->sum('cash_amount');
            
            
            //get cheque and online inflow
            $cheque_amount = CustomerLedger::whereDate('created_at', $date)->where('customer_type','buyer')->where('entry_type','cr')->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            $cash_amount = CustomerLedger::whereDate('created_at', $date)->where('customer_type','buyer')->where('entry_type','cr')->where('payment_type', 'online')->sum('cash_amount');

            $data['opening_balance'] = $openingBalance;
            $data['cash_inflow'] = $inflowSumCom;
            $data['bank_inflow'] = $cheque_amount+$cash_amount;
            $data['cash_outflow'] = $outflowSumCom;
            $data['bank_outflow'] = $cheque_amount_out+$cash_amount_out+$cheque_amount_expense_out+$cash_amount_expense_out;
            return response()->json(['date'=>$date,'data'=>$data]);
        }else{
            $openingBalance = CompanyLedger::where('link_name','opening_balance')->sum('balance');
            $inflowCashSum = CompanyLedger::where('link_name','buyer_ledger')->sum('cr_amount');
            $outflowSumCom = CompanyLedger::sum('dr_amount');

            //get cheque and online outflow
            $cheque_amount_out = CustomerLedger::where('customer_type','supplier')->whereIn('entry_type',['dr','dr&cr'])->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            $cash_amount_out = CustomerLedger::where('customer_type','supplier')->whereIn('entry_type',['dr','dr&cr'])->where('payment_type', 'online')->sum('cash_amount');
            
            //get cheque and online expense outflow
            $cheque_amount_expense_out = Expense::whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            $cash_amount_expense_out = Expense::where('payment_type', 'online')->sum('cash_amount');

            //get cheque and online inflow
            $cheque_amount = CustomerLedger::where('customer_type','buyer')->where('entry_type','cr')->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            $cash_amount = CustomerLedger::where('customer_type','buyer')->where('entry_type','cr')->where('payment_type', 'online')->sum('cash_amount');
            
            $data['opening_balance'] = $openingBalance;
            $data['cash_inflow']  = $inflowCashSum;
            $data['bank_inflow']  = $cheque_amount+$cash_amount;
            $data['cash_outflow'] = $outflowSumCom;
            $data['bank_outflow'] = $cheque_amount_out+$cash_amount_out+$cheque_amount_expense_out+$cash_amount_expense_out;;
            return response()->json(['data'=>$data]);
        }
    }

    public function drApi(Request $request)
    {

        if($request->has('start_date') && $request->has('end_date')){
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
            
            $expense_categories = ExpenseCategory::withSum(['expenses' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }], 'total_amount')->get(); 

            $data['start_date'] = $startDate;
            $data['end_date'] = $endDate;

        }else{
            $expense_categories = ExpenseCategory::withSum('expenses', 'total_amount')->get();
        }
        $data['expense_categories'] = $expense_categories;

        $suppliers = Customer::where('customer_type','supplier')->get();
        $data['suppliers'] = $suppliers;

        $banks = Bank::all();
        $data['banks'] = $banks;

        return response()->json(['data' => $data]);
    }

    public function crApi()
    {
        $buyers = Customer::where('customer_type','buyer')->get();
        $data['buyers'] = $buyers;
        return response()->json(['data' => $data]);
    }
}
