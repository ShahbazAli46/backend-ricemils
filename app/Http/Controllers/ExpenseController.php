<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Exception;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
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

            $expense = Expense::with(['expenseCategory:id,expense_category','bank:id,bank_name'])
            ->whereBetween('created_at', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $expense]);
        }else{
            $expense=Expense::with(['expenseCategory:id,expense_category','bank:id,bank_name'])->get();
            return response()->json(['data' => $expense]);
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
        $rules = [
            'expense_category_id' => ['required', 'exists:expense_categories,id', new ExistsNotSoftDeleted('expense_categories')],
            'payment_type' => 'required|in:cash,cheque,both',
            'description' => 'nullable|string',
        ];

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|min:1';
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
            $rules['cash_amount']= 'required|numeric|min:1';
        }


        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $payment_type=$request->input('payment_type');
           

            // Start a transaction
            DB::beginTransaction();
            $data_arr=[
                'expense_category_id' => $request->input('expense_category_id'),
                'payment_type' => $request->input('payment_type'),
                'description' => $request->input('description'),
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'bank_id' => $request->bank_id,
            ];
            
            $data_arr['cash_amount']= (($payment_type == 'cash' || $payment_type == 'both') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $data_arr['cheque_amount']= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            $data_arr['total_amount']=$data_arr['cash_amount']+$data_arr['cheque_amount'];
            
            $expense = Expense::create($data_arr);

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Expense Added Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Expense. ' . $e->getMessage(),
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
            $expense = Expense::with(['expenseCategory:id,expense_category','bank:id,bank_name'])->findOrFail($id);
            return response()->json(['data' => $expense]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Expense Not Found.'], Response::HTTP_NOT_FOUND);
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
            'expense_category_id' => ['required', 'exists:expense_categories,id', new ExistsNotSoftDeleted('expense_categories')],
            'payment_type' => 'required|in:cash,cheque,both',
            'description' => 'nullable|string',
        ];

        if ($request->input('payment_type') == 'cheque') {
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
        }else if($request->input('payment_type') == 'cash'){
            $rules['cash_amount']= 'required|numeric|min:1';
        }else{
            $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
            $rules['cheque_no']= 'required|string|max:100';
            $rules['cheque_date']= 'required|date';
            $rules['cheque_amount']= 'required|numeric|min:1';
            $rules['cash_amount']= 'required|numeric|min:1';
        }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            $payment_type=$request->input('payment_type');
            $expense = Expense::findOrFail($id);

            // Start a transaction
            DB::beginTransaction();
            $data_arr=[
                'expense_category_id' => $request->input('expense_category_id'),
                'payment_type' => $request->input('payment_type'),
                'description' => $request->input('description'),
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'bank_id' => $request->bank_id,
            ];

            $data_arr['cash_amount']= (($payment_type == 'cash' || $payment_type == 'both') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $data_arr['cheque_amount']= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            $data_arr['total_amount']=$data_arr['cash_amount']+$data_arr['cheque_amount'];
           
            $expense->update($data_arr);
            if (!$expense) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Update Expense Order.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            // Commit the transaction
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Expense Updated Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Expense Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message' => 'Failed to Update Expense. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = Expense::findOrFail($id);
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Expense Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Expense Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
