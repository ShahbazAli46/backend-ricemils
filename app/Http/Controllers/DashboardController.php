<?php

namespace App\Http\Controllers;

use App\Models\CompanyLedger;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Expense;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('date')){
            $date = \Carbon\Carbon::parse($request->input('date'))->startOfDay();
            $openingBalance = CompanyLedger::whereDate('created_at', $date)->first();
            $inflowSumCom = CompanyLedger::whereDate('created_at', $date)->sum('cr_amount');
            $inflowSumCus = CustomerLedger::whereDate('created_at', $date)->where('customer_type','buyer')->where('entry_type','cr')->sum('cr_amount');
            $outflowSum = CompanyLedger::whereDate('created_at', $date)->sum('dr_amount');
            $data['opening_balance'] = $openingBalance?$openingBalance->balance:0;
            $data['inflow'] = $inflowSumCom+$inflowSumCus;
            $data['outflow'] = $outflowSum;
            return response()->json(['date'=>$date,'data'=>$data]);
        }else{
            $openingBalance = CompanyLedger::first();
            $inflowSumCom = CompanyLedger::sum('cr_amount');
            $inflowSumCus = CustomerLedger::where('customer_type','buyer')->where('entry_type','cr')->sum('cr_amount');
            $outflowSum = CompanyLedger::sum('dr_amount');
            $data['opening_balance'] = $openingBalance->balance;
            $data['inflow'] = $inflowSumCom+$inflowSumCus;
            $data['outflow'] = $outflowSum;
            return response()->json(['data'=>$data]);
        }
    }

}
