<?php

namespace App\Http\Controllers;

use App\Models\Packing;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Exception;

class PackingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $packings=Packing::all();
        return response()->json(['data' => $packings]);
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
            'packing_size' => 'required|string|max:100|unique:packings',
            // 'packing_unit' => 'nullable|string|max:50'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            $packing = Packing::create([
                'packing_size' => $request->input('packing_size'),
                // 'packing_unit' => $request->input('packing_unit')
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Packing Created Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Packing. ' . $e->getMessage(),
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
            $packing = Packing::findOrFail($id);
            return response()->json(['data' => $packing]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Packing Not Found.'], Response::HTTP_NOT_FOUND);
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
            'packing_size' => 'required|string|max:100|unique:packings,packing_size,'.$id,
            // 'packing_unit' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
             // Find the packing by ID
            $packing = Packing::findOrFail($id);

            $packing->update([
                'packing_size' => $request->input('packing_size'),
                // 'packing_unit' => $request->input('packing_unit')
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Packing Updated Successfully.',
            ], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Packing Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error','message' => 'Failed to Update Packing. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = Packing::findOrFail($id);
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Packing Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Packing Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
