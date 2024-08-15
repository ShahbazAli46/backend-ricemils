<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\SaleBook;
use App\Models\SaleBookDetail;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class SaleBookController extends Controller
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

            $sale_book = SaleBook::with(['details','buyer:id,person_name'])->whereBetween('date', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $sale_book]);
        }else{
            $sale_book=SaleBook::with(['details','supplier:id,person_name'])->get();
            return response()->json(['data' => $sale_book]);
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
            'buyer_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'truck_no' => 'nullable|string|max:50',
            'date' => 'nullable|date',
            'payment_type' => 'required|in:cash,cheque,both',

            'pro_stock_id' => ['required', 'array'],
            'pro_stock_id.*' => ['required','exists:product_stocks,id'],

            'product_description' => ['nullable', 'array'],
            'product_description.*' => 'nullable|string',

            'quantity' => ['required', 'array'],
            'quantity.*' => 'required|numeric|min:1',

            'price' => ['required', 'array'],
            'price.*' => 'required|numeric|min:1',
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
            $buyer=Customer::where(['id'=>$request->buyer_id,'customer_type'=>'buyer'])->first();
            if(!$buyer){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Buyer Does Not Exist.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            //sale book detail
            $proStockID = $request->input('pro_stock_id', []);
            $productDescription = $request->input('product_description', []);
            $quantity = $request->input('quantity', []);
            $price = $request->input('price', []);
            
            $maxIndex = max(count($proStockID),count($productDescription),count($quantity),count($price));
            $isProductExist=false;
            $total_amount=0;

            for ($i = 0; $i < $maxIndex; $i++) {
                if (!empty($proStockID[$i]) && !empty($quantity[$i])  && !empty($price[$i])) {
                    $productStock=ProductStock::find($proStockID[$i]);
                    
                    $product=Product::where(['id'=>$productStock->product_id,'product_type'=>'other'])->firstOrFail();
                    if($product){
                        if($productStock->quantity<$quantity[$i]){
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Product '.$product->product_name.' Out of Stock.',
                            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
                        }
                    }else{
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Product Does Not Exist.',
                        ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
                    }

                    $isProductExist=true;
                    $total_amount+=($price[$i] * $quantity[$i]);
                }
            }

            if(!$isProductExist){
                return response()->json([
                    'status' => 'error',
                    'message' => 'You Must Add at Least One Product.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            // Start a transaction
            DB::beginTransaction();
            $add_amount=0;
            $cash_amount= ($request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            $cheque_amount= ($request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;
            
            $add_amount+= ($cash_amount+$cheque_amount);
            $rem_amount=$add_amount-$total_amount;

            $saleBook = SaleBook::create([
                'buyer_id' => $request->buyer_id,
                'truck_no' => $request->truck_no,
                'date' => $request->date,
                'payment_type' => $request->payment_type,
                'bank_id' => $request->bank_id,
                'cash_amount' => $cash_amount,
                'cheque_amount' => $cheque_amount,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'total_amount' => $total_amount,
                'rem_amount' => $rem_amount,
            ]);

            if (!$saleBook) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to Add Sale Order.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            //manage sale book detail 
            for ($i = 0; $i < $maxIndex; $i++) {
                if (!empty($proStockID[$i]) && !empty($quantity[$i])  && !empty($price[$i])) {
                    $productStock=ProductStock::with(['product','packing'])->find($proStockID[$i]);
                    $saleBookDetail = SaleBookDetail::create([
                        'sale_book_id' => $saleBook->id,
                        'pro_id' => $productStock->product_id,
                        'packing_id' => $productStock->packing_id,
                        'pro_stock_id' => $productStock->id,
                        'product_name' => $productStock->product->product_name,
                        'product_description' => $productStock->product->product_description,
                        'packing_size' => $productStock->packing->packing_size,
                        'packing_unit' => $productStock->packing->packing_unit,
                        'quantity' => $quantity[$i],
                        'price' => $price[$i],
                        'total_amount' => ($quantity[$i] * $price[$i])
                    ]);
                    if(!$saleBookDetail){
                        DB::rollBack();
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Something Went Wrong Please Try Again Later.',
                        ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
                    }
                    $productStock->update([
                        'quantity'=>($productStock->quantity-$quantity[$i])
                    ]);
                }
            }


            $transactionData=['customer_id'=>$request->buyer_id,'bank_id'=>null,'description'=>null,'dr_amount'=>$total_amount,'cr_amount'=>$add_amount,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'buyer','book_id'=>$saleBook->id,'entry_type'=>'dr&cr','balance'=>$rem_amount];
            
            if ($request->input('payment_type') == 'cheque') {
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
            }else if($request->input('payment_type') == 'cash'){
                $transactionData['cash_amount']= $cash_amount;
            }else{
                $transactionData['bank_id'] = $request->bank_id;
                $transactionData['cheque_no']= $request->cheque_no;
                $transactionData['cheque_date']= $request->cheque_date;
                $transactionData['cheque_amount']= $cheque_amount;
                $transactionData['cash_amount']= $cash_amount;
            }
            $res=$saleBook->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Something Went Wrong Please Try Again Later.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            // Commit the transaction
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Sale Order Added Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Add Sale Order. ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }
}
