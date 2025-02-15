<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $banks=Bank::withCount('advanceCheques')->withSum('advanceCheques', 'cheque_amount')->get();
        return response()->json(['data' => $banks]);
    }

    public function bankTransectionDetail(Request $request,$id){
        try {
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();

                $bank = Bank::with(['customerLedger' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                          ->with('customer'); // Load 'customer' within 'customerLedger' after filtering by date
                }])->findOrFail($id);
                
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $bank]);
            }else{
                $bank = Bank::with('customerLedger.customer')->findOrFail($id);
                return response()->json(['data' => $bank]);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Bank Not Found.'], Response::HTTP_NOT_FOUND);
        }

        $bank=Bank::with('customerLedger.customer')->find($id);
        if($bank){
            return response()->json(['data' => $bank]);
        }else{
            return response()->json(['status'=>'error', 'message' => 'Bank Not Found.'], Response::HTTP_NOT_FOUND);
        }
    }

    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100|unique:banks',
            'balance' =>  'required|numeric|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $bank = Bank::create([
                'bank_name' => $request->input('bank_name'),
                'balance' => $request->input('balance'),
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Bank Created Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Bank. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$id)
    {
        try {
            if($request->has('start_date') && $request->has('end_date')){
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();
                $bank = Bank::where('id', $id)
                ->withCount(['advanceCheques' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }])
                ->with(['advanceCheques' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->with('customer:id,person_name');  // Eager load the customer with specific fields
                }])
                ->withSum(['advanceCheques' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }], 'cheque_amount')->firstOrFail(); 
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $bank]);
            }else{
                $bank = Bank::withCount('advanceCheques')->withSum('advanceCheques', 'cheque_amount')->with(['advanceCheques.customer:id,person_name',])->findOrFail($id);
                return response()->json(['data' => $bank]);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Bank Not Found.'], Response::HTTP_NOT_FOUND);
        }
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
        $validator = Validator::make($request->all(), [            
            'bank_name' => 'required|string|max:100|unique:banks,bank_name,'.$id,
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
             // Find the bank by ID
            $bank = Bank::findOrFail($id);
            $bank->update([
                'bank_name' => $request->input('bank_name'),
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Bank Updated Successfully.',
            ], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Bank Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error','message' => 'Failed to Update Bank. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $resource = Bank::findOrFail($id);
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Bank Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Bank Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
