<?php

namespace App\Http\Controllers;

use App\Models\CompanyLedger;
use App\Models\Expense;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('date')){
            $date = \Carbon\Carbon::parse($request->input('date'))->startOfDay();
            $openingBalance = CompanyLedger::whereDate('created_at', $date)->first();
            $inflowSum = CompanyLedger::whereDate('created_at', $date)->sum('cr_amount');
            $outflowSum = CompanyLedger::whereDate('created_at', $date)->sum('dr_amount');
            $data['opening_balance'] = $openingBalance->balance;
            $data['inflow'] = $inflowSum;
            $data['outflow'] = $outflowSum;
            return response()->json(['date'=>$date,'data'=>$data]);
        }else{
            $openingBalance = CompanyLedger::first();
            $inflowSum = CompanyLedger::sum('cr_amount');
            $outflowSum = CompanyLedger::sum('dr_amount');
            $data['opening_balance'] = $openingBalance->balance;
            $data['inflow'] = $inflowSum;
            $data['outflow'] = $outflowSum;
            return response()->json(['data'=>$data]);
        }
        
    }

}
