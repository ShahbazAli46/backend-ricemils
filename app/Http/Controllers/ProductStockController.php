<?php

namespace App\Http\Controllers;

use App\Models\Packing;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class ProductStockController extends Controller
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

            $product_stock = ProductStock::whereBetween('created_at', [$startDate, $endDate])->get();
            // with(['product:id,product_name','packing:id,packing_size,packing_unit'])->
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $product_stock]);
        }else{
            $product_stock=ProductStock::all();
            // with(['product:id,product_name','packing:id,packing_size,packing_unit'])->get();
            return response()->json(['data' => $product_stock]);
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
            'product_id.*' => ['required','exists:products,id',new ExistsNotSoftDeleted('products')],
            'packing_id.*' => ['required','exists:packings,id',new ExistsNotSoftDeleted('packings')],
            'product_description.*' => 'nullable|string',
            'quantity.*' => 'required|numeric|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        // $productStocks = [];
        try {
            $productIds = $request->product_id;
            // Check for duplicate product IDs
            if (count($productIds) !== count(array_unique($productIds))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Duplicate Products are Not Allowed.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            // Start a transaction
            DB::beginTransaction();

            foreach ($productIds as $index => $productId) {
                //add check here product type must be other
                $product = Product::findOrFail($productId);
                $packing = Packing::findOrFail($request->packing_id[$index]);
                $isStockExist=ProductStock::where(['product_id'=>$productId,'packing_id'=>$packing->id])->first();
                if($isStockExist){
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Product Already Exist in Stock, But You Can Edit Your Stock.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                
                $productStock = ProductStock::create([
                    'product_id' => $productId,
                    'packing_id' => $packing->id,
                    'product_name' => $product->product_name,
                    'product_description' => $request->input('product_description')[$index],
                    'packing_size' => $packing->packing_size,
                    'packing_unit' => $packing->packing_unit,
                    'quantity' => $request->input('quantity')[$index],
                ]);

                if (!$productStock) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to Add Some Stock Entries.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }

                // $productStocks[] = $productStock;
            }

            // Commit the transaction
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Stock Added Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Stock. ' . $e->getMessage(),
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
            $product_stock = ProductStock::findOrFail($id);
            // with(['product:id,product_name','packing:id,packing_size,packing_unit'])->
            return response()->json(['data' => $product_stock]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Stock Not Found.'], Response::HTTP_NOT_FOUND);
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
            'product_id' => ['required','exists:products,id',new ExistsNotSoftDeleted('products')],
            'packing_id' => ['required','exists:packings,id',new ExistsNotSoftDeleted('packings')],
            'product_description' => 'nullable|string',
            'quantity' => 'required|numeric|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
             // Find the product stock by ID
            $product_stock = ProductStock::findOrFail($id);
            $product = Product::findOrFail($request->product_id);
            $packing = Packing::findOrFail($request->packing_id);

            $product_stock->update([
                'product_id' => $product->id,
                'packing_id' => $packing->id,
                'product_name' => $product->product_name,
                'product_description' => $request->input('product_description'),
                'packing_size' => $packing->packing_size,
                'packing_unit' => $packing->packing_unit,
                'quantity' => $request->input('quantity'),
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Stock Updated Successfully.',
            ], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Stock Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error','message' => 'Failed to Update Stock. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = ProductStock::findOrFail($id);
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Stock Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Stock Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
