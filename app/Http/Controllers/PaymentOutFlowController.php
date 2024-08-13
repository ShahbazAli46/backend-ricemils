<?php

namespace App\Http\Controllers;

use App\Models\PaymentFlow;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Exception;
use App\Rules\ExistsNotSoftDeleted;

class PaymentOutFlowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->has('start_date') && $request->has('end_date')){
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $payment_out = PaymentFlow::with(['customer:id,person_name','bank:id,bank_name'])
            ->whereBetween('created_at', [$startDate, $endDate])->where('payment_flow_type','PO')->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $payment_out]);
        }else{
            $payment_out=PaymentFlow::with(['customer:id,person_name','bank:id,bank_name'])->where('payment_flow_type','PO')->get();
            return response()->json(['data' => $payment_out]);
        }
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
        $rules = [
            'customer_id' => ['required', 'exists:customers,id', new ExistsNotSoftDeleted('customers')],
            'payment_type' => 'required|in:cash,cheque',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string',
        ];

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $data_arr=[
                'customer_id' => $request->input('customer_id'),
                'payment_type' => $request->input('payment_type'),
                'amount' => $request->input('amount'),
                'description' => $request->input('description'),
                'payment_flow_type' => 'PO',
            ];

            if ($request->input('payment_type') == 'cheque') {
                $data_arr['bank_id']=$request->input('bank_id');
                $data_arr['cheque_no']=$request->input('cheque_no');
                $data_arr['cheque_date']=$request->input('cheque_date');
            }

            $payment_out = PaymentFlow::create($data_arr);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment Out Flow Created Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Payment Out Flow. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $payment_out = PaymentFlow::with(['customer:id,person_name','bank:id,bank_name'])->where('payment_flow_type','PO')->where('id',$id)->firstOrFail();
            return response()->json(['data' => $payment_out]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Payment Out Flow Not Found.'], Response::HTTP_NOT_FOUND);
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
        $rules = [
            'customer_id' => ['required', 'exists:customers,id', new ExistsNotSoftDeleted('customers')],
            'payment_type' => 'required|in:cash,cheque',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string',
        ];

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            $payment_out = PaymentFlow::where('payment_flow_type','PO')->where('id',$id)->firstOrFail();
            
            $data_arr=[
                'customer_id' => $request->input('customer_id'),
                'payment_type' => $request->input('payment_type'),
                'amount' => $request->input('amount'),
                'description' => $request->input('description'),
            ];

            if ($request->input('payment_type') == 'cheque') {
                $data_arr['bank_id']=$request->input('bank_id');
                $data_arr['cheque_no']=$request->input('cheque_no');
                $data_arr['cheque_date']=$request->input('cheque_date');
            }

            $payment_out->update($data_arr);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Payment Out Flow Updated Successfully.',
            ], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Payment Out Flow Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error','message' => 'Failed to Update Payment Out Flow. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = PaymentFlow::where('payment_flow_type','PO')->where('id',$id)->firstOrFail();
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Payment Out Flow Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Payment Out Flow Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
