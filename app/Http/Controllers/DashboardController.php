<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankLedger;
use App\Models\CompanyLedger;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PurchaseBook;
use App\Models\SaleBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('date')){
            $date = \Carbon\Carbon::parse($request->input('date'))->startOfDay();
            $openingBalanceRecord = CompanyLedger::where('created_at', '<', $date)->orderBy('created_at', 'desc')->first();
            
            if($openingBalanceRecord){
                $todayOpeningBalance = CompanyLedger::whereDate('created_at', $date)->where('link_name','opening_balance')->sum('cr_amount');
                $openingBalance = $openingBalanceRecord->balance+$todayOpeningBalance;
            }else{
                $openingBalance = CompanyLedger::whereDate('created_at', $date)->where('link_name','opening_balance')->sum('cr_amount');
            }

            $inflowSumCom = CompanyLedger::whereDate('created_at', $date)
            ->where(function($query) {
                $query->whereIn('link_name', ['party_ledger', 'investor_ledger', 'bank_to_cash_self'])
                      ->orWhere(function($query) {
                          $query->where('link_name', 'product_expense')->where('entry_type', 'cr');
                      });
            })->sum('cr_amount');
            $outflowSumCom = CompanyLedger::whereDate('created_at', $date)->sum('dr_amount');

            //get cheque and online outflow
            // $cheque_amount_out = CustomerLedger::whereDate('created_at', $date)->where('customer_type','party')->whereIn('entry_type',['cr','dr&cr'])->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_out = CustomerLedger::whereDate('created_at', $date)->where('customer_type','party')->whereIn('entry_type',['cr','dr&cr'])->where('payment_type', 'online')->sum('cash_amount');
            
            // $cheque_amount_out_investor = CustomerLedger::whereDate('created_at', $date)->where('customer_type','investor')->whereIn('entry_type',['cr','dr&cr'])->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_out_investor = CustomerLedger::whereDate('created_at', $date)->where('customer_type','investor')->whereIn('entry_type',['cr','dr&cr'])->where('payment_type', 'online')->sum('cash_amount');
            
            //get cheque and online expense outflow
            // $cheque_amount_expense_out = Expense::whereDate('created_at', $date)->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_expense_out = Expense::whereDate('created_at', $date)->where('payment_type', 'online')->sum('cash_amount');
            
            //get cheque and online inflow
            // $cheque_amount = CustomerLedger::whereDate('created_at', $date)->where('customer_type','party')->where('entry_type','dr')->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount = CustomerLedger::whereDate('created_at', $date)->where('customer_type','party')->where('entry_type','dr')->where('payment_type', 'online')->sum('cash_amount');
           
            // $cheque_amount_investor = CustomerLedger::whereDate('created_at', $date)->where('customer_type','investor')->where('entry_type','dr')->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_investor = CustomerLedger::whereDate('created_at', $date)->where('customer_type','investor')->where('entry_type','dr')->where('payment_type', 'online')->sum('cash_amount');
            
            $bank_inflow=BankLedger::whereDate('created_at', $date)->sum('cr_amount');
            $bank_outflow=BankLedger::whereDate('created_at', $date)->sum('dr_amount');

            $data['opening_balance'] = $openingBalance;
            $data['cash_inflow'] = $inflowSumCom;
            $data['bank_inflow'] = $bank_inflow;//($cheque_amount+$cash_amount)+($cheque_amount_investor+$cash_amount_investor);
            $data['cash_outflow'] = $outflowSumCom;
            $data['bank_outflow'] = $bank_outflow;//$cheque_amount_out+$cash_amount_out+$cheque_amount_expense_out+$cash_amount_expense_out+($cheque_amount_out_investor+$cash_amount_out_investor);
            
            return response()->json(['date'=>$date,'data'=>$data]);
        }else{
            $openingBalance = CompanyLedger::where('link_name','opening_balance')->sum('cr_amount');
            $inflowCashSum = CompanyLedger::where(function($query) {
                $query->whereIn('link_name', ['party_ledger', 'investor_ledger', 'bank_to_cash_self'])
                      ->orWhere(function($query) {
                          $query->where('link_name', 'product_expense')->where('entry_type', 'cr');
                      });
            })->sum('cr_amount');
            $outflowSumCom = CompanyLedger::sum('dr_amount');

            //get cheque and online outflow
            // $cheque_amount_out = CustomerLedger::where('customer_type','party')->whereIn('entry_type',['cr','dr&cr'])->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_out = CustomerLedger::where('customer_type','party')->whereIn('entry_type',['cr','dr&cr'])->where('payment_type', 'online')->sum('cash_amount');
            // $cheque_amount_out_investor = CustomerLedger::where('customer_type','investor')->whereIn('entry_type',['cr','dr&cr'])->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_out_investor = CustomerLedger::where('customer_type','investor')->whereIn('entry_type',['cr','dr&cr'])->where('payment_type', 'online')->sum('cash_amount');
            
            //get cheque and online expense outflow
            // $cheque_amount_expense_out = Expense::whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_expense_out = Expense::where('payment_type', 'online')->sum('cash_amount');

            //get cheque and online inflow
            // $cheque_amount = CustomerLedger::where('customer_type','party')->where('entry_type','dr')->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount = CustomerLedger::where('customer_type','party')->where('entry_type','dr')->where('payment_type', 'online')->sum('cash_amount');
            
            // $cheque_amount_investor = CustomerLedger::where('customer_type','investor')->where('entry_type','dr')->whereIn('payment_type', ['cheque', 'both'])->sum('cheque_amount');
            // $cash_amount_investor = CustomerLedger::where('customer_type','investor')->where('entry_type','dr')->where('payment_type', 'online')->sum('cash_amount');

            $bank_inflow=BankLedger::sum('cr_amount');
            $bank_outflow=BankLedger::sum('dr_amount');

            $data['opening_balance'] = $openingBalance;
            $data['cash_inflow']  = $inflowCashSum;
            $data['bank_inflow'] = $bank_inflow;//($cheque_amount+$cash_amount)+($cheque_amount_investor+$cash_amount_investor);
            $data['cash_outflow'] = $outflowSumCom;
            $data['bank_outflow'] = $bank_outflow;//$cheque_amount_out+$cash_amount_out+$cheque_amount_expense_out+$cash_amount_expense_out+($cheque_amount_out_investor+$cash_amount_out_investor);
           
            return response()->json(['data'=>$data]);
        }
    }

    public function drApi(Request $request)
    {
        $expense_categories = ExpenseCategory::query();
        if($request->has('start_date') && $request->has('end_date')){
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
            
            $expense_categories = $expense_categories->withSum(['expenses as total_add' => function ($query) use ($startDate, $endDate, $request) {
                $query->whereBetween('created_at', [$startDate, $endDate])->where('expense_entry_type', 'add');
                if($request->has('season_id')){
                    $query->where('season_id', $request->season_id);
                }
            }], 'total_amount')
            ->withSum(['expenses as total_return' => function ($query) use ($startDate, $endDate, $request) {
                $query->whereBetween('created_at', [$startDate, $endDate])->where('expense_entry_type', 'return');
                if($request->has('season_id')){
                    $query->where('season_id', $request->season_id);
                }
            }], 'total_amount')->get(); 

            $data['start_date'] = $startDate;
            $data['end_date'] = $endDate;
        }else{
            $expense_categories = $expense_categories->withSum(['expenses as total_add' => function ($query) use ($request) {
                $query->where('expense_entry_type', 'add');
                if($request->has('season_id')){
                    $query->where('season_id', $request->season_id);
                }
            }], 'total_amount')
            ->withSum(['expenses as total_return' => function ($query) use ($request) {
                $query->where('expense_entry_type', 'return');
                if($request->has('season_id')){
                    $query->where('season_id', $request->season_id);
                }
            }], 'total_amount')->get();
        }

        $data['expense_categories'] = $expense_categories;

        $parties = Customer::where('customer_type','party')->get();
        $data['parties'] = $parties;

        $banks = Bank::all();
        $data['banks'] = $banks;

        $cash_amt = CompanyLedger::select('balance')->latest()->first()->balance ?? 0.00;
        $data['cash'] = $cash_amt;

        return response()->json(['data' => $data]);
    }

    public function crApi()
    {
        $parties = Customer::where('customer_type','party')->get();
        $data['parties'] = $parties;
        return response()->json(['data' => $data]);
    }


    public function getOverallAmtInfo(Request $request){
        //get total payable and receiveable amount
        $data['total_payable_amt'] = 0;
        $data['total_receivable_amt'] = 0;
        
        $lastEntriesSubQuery = function ($query) {
            $query->selectRaw('MAX(id)')->from('customer_ledgers')->groupBy('customer_id');
        };

        $balances = CustomerLedger::whereIn('id', $lastEntriesSubQuery)
            ->whereIn('customer_type', ['investor', 'party'])->get()->groupBy('customer_type');

        // Investor
        $investorBalance = $balances->get('investor', collect())->sum('balance');
        $payableFromInvestor = max($investorBalance, 0);
        $receivableFromInvestor = min($investorBalance, 0);

        // Party
        $partyBalance = $balances->get('party', collect())->sum('balance');
        $receivableFromParty = max($partyBalance, 0);
        $payableFromParty = min($partyBalance, 0);

        $data['total_payable_amt'] = number_format($payableFromInvestor + $payableFromParty, 2, '.', '');
        $data['total_receivable_amt'] = number_format($receivableFromInvestor + $receivableFromParty, 2, '.', '');
        $expenseQuery = Expense::query();

        $purchaseQuery = PurchaseBook::query();
        $saleQuery = SaleBook::query();

        if($request->has('season_id')){
            $expenseQuery->where('season_id', $request->season_id);
            $purchaseQuery->where('season_id', $request->season_id);
            $saleQuery->where('season_id', $request->season_id);
        }
        $totalPurchaseAmt = $purchaseQuery->sum('total_amount');
        $totalSaleAmt = $saleQuery->sum('total_amount');
        $data['total_stock_in_hand_amt']= (string)($totalPurchaseAmt-$totalSaleAmt);
        $data['total_expense_amt'] =(string) (
            $expenseQuery->where('expense_entry_type','add')->sum('total_amount')
          - $expenseQuery->where('expense_entry_type','return')->sum('total_amount')
        );
        $data['total_bank_balance'] = Bank::sum('balance');
        return response()->json(['data' => $data]);
    }
}
