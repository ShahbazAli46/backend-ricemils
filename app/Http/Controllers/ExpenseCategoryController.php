<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $expense_categories = ExpenseCategory::withSum('expenses', 'total_amount')->get();
        return response()->json(['data' => $expense_categories]); 
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
            'expense_category' => 'required|string|max:100|unique:expense_categories',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $expense_category = ExpenseCategory::create([
                'expense_category' => $request->input('expense_category'),
            ]);
    
            return response()->json(['status' => 'success','message' => 'Expense Category Created Successfully.',], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            return response()->json(['status' => 'error','message' => 'Failed to Create Expense Category. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
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
                
                $expense_category = ExpenseCategory::where('id', $id)
                ->with(['expenses' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }])
                ->withSum(['expenses' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }], 'total_amount')
                ->firstOrFail(); 
                return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $expense_category]);
            }else{
                $expense_category = ExpenseCategory::withSum('expenses', 'total_amount')->with(['expenses'])->findOrFail($id);
                return response()->json(['data' => $expense_category]);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Expense Category Not Found.'], Response::HTTP_NOT_FOUND);
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
            'expense_category' => 'required|string|max:100|unique:expense_categories,expense_category,'.$id,
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
             // Find the Expense Category by ID
            $expense_category = ExpenseCategory::findOrFail($id);
            $expense_category->update([
                'expense_category' => $request->input('expense_category'),
            ]);
    
            return response()->json(['status' => 'success','message' => 'Expense Category Updated Successfully.',], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Expense Category Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error','message' => 'Failed to Update Expense Category. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = ExpenseCategory::findOrFail($id);
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Expense Category Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Expense Category Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
