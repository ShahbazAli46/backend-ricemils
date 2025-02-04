<?php

namespace App\Http\Controllers;

use App\Models\CompanyProductStock;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $products=Product::with(['companyProductStocks' => function ($query) {
            $query->select('id', 'product_id', 'total_weight', 'remaining_weight','balance')
                ->whereIn('id', function ($subQuery) {
                    $subQuery->select(DB::raw('MAX(id)'))->from('company_product_stocks')->groupBy('product_id');
                });
            }])->get();
        return response()->json(['data' => $products]);
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
            'product_name' => 'required|string|max:100',
            'product_description' => 'nullable|string',
            'opening_weight' => 'nullable|numeric|min:0',
            'opening_price' => 'nullable|numeric|min:0',
            'opening_price_mann' => 'nullable|numeric|min:0',
            'opening_total_amount' => 'nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            DB::beginTransaction();
            $product = Product::create([
                'product_name' => $request->input('product_name'),
                'product_description' => $request->input('product_description'),
            ]);
            
            // Add company stock entry
            $stock = CompanyProductStock::where(['product_id'=> $request->pro_id])->latest()->first();
            $old_total_weight=$stock?$stock->total_weight:0;
            $old_remaining_weight=$stock?$stock->remaining_weight:0;
            CompanyProductStock::create([
                'product_id' => $product->id,
                'total_weight' => $old_total_weight+$request->opening_weight,
                'stock_in' => $request->opening_weight,
                'remaining_weight' =>  $old_remaining_weight+$request->opening_weight,
                'entry_type' => 'opening',
                'price' => $request->opening_price,
                'price_mann' => $request->opening_price_mann,
                'total_amount' => $request->opening_total_amount,
                'balance' => $request->opening_total_amount,
            ]);

            DB::commit();
            return response()->json(['status' => 'success','message' => 'Product Created Successfully.'], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Create Product. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
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
            $product = Product::with(['companyProductStocks' => function ($query) {
            $query->select('id', 'product_id', 'total_weight', 'remaining_weight','balance')
                ->whereIn('id', function ($subQuery) {
                    $subQuery->select(DB::raw('MAX(id)'))->from('company_product_stocks')->groupBy('product_id');
                });
            }])->findOrFail($id);

            $com_pro_stock_query=CompanyProductStock::with('party')->where('product_id',$id);
            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay(); // Use endOfDay to include the entire day
                $com_pro_stock_query->whereBetween('created_at', [$startDate, $endDate]);
            }
            $product->company_product_stock_details=$com_pro_stock_query->get();
            
            return response()->json(['data' => $product]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Product Not Found.'], Response::HTTP_NOT_FOUND);
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
            'product_name' => 'required|string|max:100',
            'product_description' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
             // Find the product by ID
            $product = Product::findOrFail($id);
            $product->update([
                'product_name' => $request->input('product_name'),
                'product_description' => $request->input('product_description')
            ]);
    
            return response()->json(['status' => 'success','message' => 'Product Updated Successfully.',], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Product Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error','message' => 'Failed to Update Product. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = Product::findOrFail($id);
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Product Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Product Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
