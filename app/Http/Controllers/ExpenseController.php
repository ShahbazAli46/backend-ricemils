<?php

namespace App\Http\Controllers;

use App\Models\PaymentFlow;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Exception;
use App\Rules\ExistsNotSoftDeleted;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // if($request->has('start_date') && $request->has('end_date')){
        //     $startDate = $request->input('start_date');
        //     $endDate = $request->input('end_date');

        //     $payment_in = PaymentFlow::with(['customer:id,person_name','bank:id,bank_name'])
        //     ->whereBetween('created_at', [$startDate, $endDate])->where('payment_flow_type','PI')->get();
        //     return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $payment_in]);
        // }else{
        //     $payment_in=PaymentFlow::with(['customer:id,person_name','bank:id,bank_name'])->where('payment_flow_type','PI')->get();
        //     return response()->json(['data' => $payment_in]);
        // }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
