<?php

namespace App\Http\Controllers;

use App\Models\CompanyLedger;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Exception;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;


class CompanyLedgerController extends Controller
{
     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'payment_type' => 'required|in:cash',
            'description' => 'nullable|string|max:255',
            'cash_amount' => 'required|numeric|min:1',
        ];

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            DB::beginTransaction();
            $data_arr=[
                'payment_type' => $request->input('payment_type'),
                'description' => $request->input('description'),
                'cash_amount' => $request->cash_amount,
                'total_amount' => $request->cash_amount,
            ];
                        
            $transactionDataComp=['dr_amount'=>0.00,'cr_amount'=>$data_arr['total_amount'],'description'=>$request->description,'entry_type'=>'cr','link_id'=>null,'link_name'=>'opening_balance'];
            $company_ledger =new CompanyLedger();
            $res=$company_ledger->addCompanyTransaction($transactionDataComp);
            
            if(!$res){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Ledger Added Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Ledger. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }
}
