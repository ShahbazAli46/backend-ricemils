<?php

namespace App\Http\Controllers;

use App\Models\SeasonSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;

class SeasonSummaryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $season_summaries = SeasonSummary::all();
        return response()->json(['data' => $season_summaries]); 
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
            'name' => 'required|string|max:250|unique:season_summaries',
            'description' => 'nullable|string|max:250',
            'sale_amount' => 'nullable|numeric|min:0',
            'purchase_amount' => 'nullable|numeric|min:0',
            'expense_amount' => 'nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first()],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $season_summary = SeasonSummary::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'sale_amount' => $request->input('sale_amount',0),
                'purchase_amount' => $request->input('purchase_amount',0),
                'expense_amount' => $request->input('expense_amount',0),
            ]);
    
            return response()->json(['status' => 'success','message' => 'Season Summary Created Successfully.',], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            return response()->json(['status' => 'error','message' => 'Failed to Create Season Summary. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
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
            $season_summary = SeasonSummary::findOrFail($id);
            return response()->json(['data' => $season_summary]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Season Summary Not Found.'], Response::HTTP_NOT_FOUND);
        }
    }

}
