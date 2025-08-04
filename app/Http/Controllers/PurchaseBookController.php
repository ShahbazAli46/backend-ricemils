<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanyLedger;
use App\Models\CompanyProductStock;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseBook;
use App\Models\SeasonSummary;
use App\Models\User;
use App\Rules\CheckBankBalance;
use App\Rules\CheckCashBalance;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class PurchaseBookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->has('start_date') && $request->has('end_date')){
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();

            $purchase_book = PurchaseBook::with(['product:id,product_name','party:id,person_name','user:'. User::SELECT_BASIC_STRING,'season:id,name'])->whereBetween('created_at', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $purchase_book]);
        }else{
            $purchase_book=PurchaseBook::with(['product:id,product_name','party:id,person_name','user:'. User::SELECT_BASIC_STRING,'season:id,name'])->get();
            return response()->json(['data' => $purchase_book]);
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
            'sup_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'pro_id' => ['required','exists:products,id',new ExistsNotSoftDeleted('products')],
            
            'bardaana_type' => 'required|in:add,return,paid_as_cash,paid_as_ledger',
            'bardaana_entry' => 'required',
            'bardaana_amount' => 'nullable|numeric|min:0',
            'total_bardaana_quantity' => 'required|numeric|min:1',
            'bardaana_quantity' => 'required|numeric|min:0',

            'truck_no' => 'nullable|string|max:50',
            'net_weight' => 'required|numeric|min:1',
            'khoot' => 'required|numeric|min:0',
            'chungi' => 'required|numeric|min:0',
            'bardaana_deduction' => 'required|numeric|min:0',
            'freight' => 'required|numeric|min:0',
            'price_mann' => 'required|numeric|min:1',
            'date' => 'nullable|date',
            // 'payment_type' => 'required|in:cash,cheque,both,online',
            'description'=> 'nullable|string',
            'season_id' => ['required', 'exists:season_summaries,id'],
        ];   
        
        // if ($request->input('payment_type') == 'cheque') {
        //     $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
        //     $rules['cheque_no']= 'required|string|max:100';
        //     $rules['cheque_date']= 'required|date';
        //     $rules['bank_tax']= 'required|numeric|min:0';
        //     $rules['cheque_amount']= ['required','numeric','min:1', new CheckBankBalance($request->input('bank_id'))];
        // }else if($request->input('payment_type') == 'cash'){
        //     $rules['cash_amount']= ['required','numeric','min:0',new CheckCashBalance()];
        // }else if($request->input('payment_type') == 'online'){
        //     $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
        //     $rules['cash_amount']= ['required','numeric','min:1', new CheckBankBalance($request->input('bank_id'))];
        //     $rules['transection_id']= 'required|string|max:100';
        //     $rules['bank_tax']= 'required|numeric|min:0';
        // }else{
        //     $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
        //     $rules['cash_amount']= ['required','numeric','min:1',new CheckCashBalance()];
        //     $rules['cheque_amount']= ['required','numeric','min:1', new CheckBankBalance($request->input('bank_id'))];

        //     $rules['cheque_no']= 'required|string|max:100';
        //     $rules['cheque_date']= 'required|date';
        //     $rules['bank_tax']= 'required|numeric|min:0';
        // }

        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json(['status' => 'error','message' => $validator->errors()->first(),],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }

        try {
            // $payment_type=$request->input('payment_type');
            $supplier=Customer::where(['id'=>$request->sup_id,'customer_type'=>'party'])->first();
            if(!$supplier){
                return response()->json(['status' => 'error','message' => 'Party Does Not Exist.'], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            $product=Product::where(['id'=>$request->pro_id])->first();
            if(!$product){
                return response()->json(['status' => 'error','message' => 'Product Does Not Exist.'], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }

            // Start a transaction
            DB::beginTransaction();
            $comp_debit_amt=0;
            // calculate final weight,price and weight_per_bag
            $final_weight=$request->net_weight-($request->khoot+$request->chungi+$request->bardaana_deduction);
            $price = ($request->price_mann * $final_weight) / 40;
            $weight_per_bag=$final_weight/$request->total_bardaana_quantity;
            
            $total_amount=$price;

            // $add_amount=0;
            // $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
            // $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;

            $lastLedger = $supplier->ledgers()->orderBy('id', 'desc')->first();
            $previousBalance=0.00;
            if($lastLedger){
                $previousBalance=$lastLedger->balance;
            }else{
                $previousBalance=$supplier->opening_balance;
            }

            // $add_amount+= ($cash_amount+$cheque_amount);

            $totalWithPreBlnc=$previousBalance-$total_amount;
            $rem_amount=$total_amount;
            $rem_blnc_amount=$totalWithPreBlnc;
            $purchaseBook = PurchaseBook::create([
                'sup_id' => $request->sup_id,
                'pro_id' => $request->pro_id,
                'bardaana_type' => $request->bardaana_type,
                'bardaana_entry' => $request->bardaana_entry,
                'truck_no' => $request->truck_no,
                'net_weight' => $request->net_weight,
                'khoot' => $request->khoot,
                'chungi' => $request->chungi,
                'bardaana_deduction' => $request->bardaana_deduction,
                'bardaana_amount' => $request->filled('bardaana_amount')?$request->bardaana_amount:0,
                'final_weight' => $final_weight,
                'bardaana_quantity' => $request->total_bardaana_quantity,
                'weight_per_bag' => $weight_per_bag,
                'freight' => $request->freight,
                'price' => $price,
                'price_mann' => $request->price_mann,
                // 'bank_tax' => $request->bank_tax,
                'date' => $request->input('date', now()),
                'payment_type' => 'none',
                'bank_id' => $request->bank_id,
                // 'cash_amount' => $cash_amount,
                // 'cheque_amount' => $cheque_amount,
                // 'cheque_no' => $request->cheque_no,
                // 'cheque_date' => $request->cheque_date,
                // 'transection_id' => $payment_type=='online'?$request->transection_id:null,
                // 'net_amount' => $add_amount,
                'total_amount' => $total_amount,
                'rem_amount' => $rem_amount,
                'description' => $request->description,
                'user_id' => auth()->id(),
                'season_id' => $request->season_id
            ]);

            if (!$purchaseBook) {
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Failed to Add Purchase Order.',], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }
            

            SeasonSummary::where('id', $request->season_id)->increment('purchase_amount', $purchaseBook->total_amount);

            $transactionData=['customer_id'=>$request->sup_id,'bank_id'=>null,'description'=>$request->description,'dr_amount'=>$total_amount,'cr_amount'=>0.00,
            'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>null,'cheque_amount'=>0.00,
            'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null,'customer_type'=>'party','book_id'=>$purchaseBook->id,'entry_type'=>'dr','balance'=>$rem_blnc_amount];
            
            // if ($request->input('payment_type') == 'cheque') {
            //     $transactionData['bank_id'] = $request->bank_id;
            //     $transactionData['cheque_no']= $request->cheque_no;
            //     $transactionData['cheque_date']= $request->cheque_date;
            //     $transactionData['cheque_amount']= $cheque_amount;
            //     $transactionData['bank_tax']= $request->bank_tax;
            // }else if($request->input('payment_type') == 'cash'){
            //     $comp_debit_amt+=$cash_amount;
            //     $transactionData['cash_amount']= $cash_amount;
            // }else if($request->input('payment_type') == 'online'){
            //     $transactionData['cash_amount']= $cash_amount;;
            //     $transactionData['transection_id']= $request->transection_id;
            //     $transactionData['bank_id'] = $request->bank_id;
            //     $transactionData['bank_tax']= $request->bank_tax;
            // }else{
            //     $comp_debit_amt+=$cash_amount;
            //     $transactionData['bank_id'] = $request->bank_id;
            //     $transactionData['cheque_no']= $request->cheque_no;
            //     $transactionData['cheque_date']= $request->cheque_date;
            //     $transactionData['cheque_amount']= $cheque_amount;
            //     $transactionData['cash_amount']= $cash_amount;
            //     $transactionData['bank_tax']= $request->bank_tax;
            // }
            $res=$purchaseBook->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }

            $bardaana_amt=0;
            if($request->bardaana_type=='add'){
                $purchaseBook->purchaseBookBardaanaDetails()->create([
                    'sup_id' => $request->sup_id,
                    'bardaana_entry' => $request->bardaana_entry,
                    'bardaana_type' => $request->bardaana_type,
                    'total_bardaana_qty' => $request->bardaana_quantity,
                    'bardaana_qty' => $request->bardaana_quantity,
                    'remaining_bardaana_qty' => $request->bardaana_quantity,
                    'bardaana_paid_amt' => 0,
                    'date' => $request->input('date', now()),
                    'user_id' => auth()->id(),
                    'season_id' => $request->season_id
                ]);
            }else if($request->bardaana_type=='paid_as_cash' && $request->bardaana_amount>0){
                $bardaana_amt=$request->bardaana_amount;

                $expenseCategory=ExpenseCategory::firstOrCreate(
                    ['expense_category' => 'Bardaana Expense'],
                    ['expense_category' => 'Bardaana Expense']
                );

                $expense=Expense::create([
                    'expense_category_id' => $expenseCategory->id,
                    'payment_type' => 'cash',
                    'description' => $request->input('description'),
                    'cash_amount' => $bardaana_amt,
                    'date' => $request->input('date', now()),
                    'total_amount'=>$bardaana_amt,
                    'user_id' => auth()->id(),
                    'season_id' => $request->season_id
                ]);

                //company ledger
                $transactionDataComp=['dr_amount'=>$bardaana_amt,'cr_amount'=>0.00,'description'=>$request->description,'entry_type'=>'dr','link_id'=>$expense->id,'link_name'=>'expense'];
                $res=$expense->addCompanyTransaction($transactionDataComp);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
            }else if($request->bardaana_type=='paid_as_ledger' && $request->bardaana_amount>0){
                $bardaana_amt=$request->bardaana_amount;
                $lastLedger = $supplier->ledgers()->orderBy('id', 'desc')->first();
                $previousBalance=0.00;
                if($lastLedger){
                    $previousBalance=$lastLedger->balance;
                }else{
                    $previousBalance=$supplier->opening_balance;
                }

                $transactionData=['customer_id'=>$request->sup_id,'bank_id'=>null,'description'=>'Bardaana Payment','dr_amount'=>$bardaana_amt,'cr_amount'=>0.00,
                'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>null,'cheque_amount'=>0.00,
                'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null,'customer_type'=>'party','book_id'=>$purchaseBook->id,'entry_type'=>'dr','balance'=>$previousBalance-$bardaana_amt];
                $res=$purchaseBook->addTransaction($transactionData);
                if(!$res){
                    DB::rollBack();
                    return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
                }
            }
            
            // if($request->input('payment_type') == 'cash' || $request->input('payment_type')=='both'){
            //     //company ledger
            //     $transactionDataComp=['dr_amount'=>$comp_debit_amt,'cr_amount'=>0.00,'description'=>$request->description,'entry_type'=>'dr','link_id'=>$purchaseBook->id,'link_name'=>'purchase'];
            //     $res=$purchaseBook->addCompanyTransaction($transactionDataComp);
            //     if(!$res){
            //         DB::rollBack();
            //         return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            //     }
            // }
            $total_amount=$total_amount+$bardaana_amt;
            // Add company stock entry
            $stock_balance = CompanyProductStock::where(['product_id'=> $request->pro_id])->latest()->first();
            $old_balance=$stock_balance?$stock_balance->balance:0;

            $stock = CompanyProductStock::where(['product_id'=> $request->pro_id])->whereIn('entry_type',['opening','purchase','sale'])->latest()->first();
            $old_total_weight=$stock?$stock->total_weight:0;
            $old_remaining_weight=$stock?$stock->remaining_weight:0;
            CompanyProductStock::create([
                'product_id' => $request->pro_id,
                'total_weight' => $old_total_weight+$final_weight,
                'stock_in' => $final_weight,
                'remaining_weight' =>  $old_remaining_weight+$final_weight,
                'linkable_id' => $purchaseBook->id,
                'linkable_type' => 'App\Models\PurchaseBook',
                'entry_type' => 'purchase',
                'price' => $price,
                'price_mann' => $request->price_mann,
                'bardaana_amt' => $bardaana_amt,
                'total_amount' => $total_amount,
                'balance' => $old_balance+$total_amount,
                'party_id' => $purchaseBook->sup_id,
                'user_id' => auth()->id(),
            ]);

            // Commit the transaction
            DB::commit();
            return response()->json(['status' => 'success','message' => 'Purchase Order Added Successfully.','data'=>$purchaseBook], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Purchase Order. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    public function bardaanaReturn(Request $request){
        try {
            $rules = [
                'purchase_book_id' => ['required', 'exists:purchase_book,id'],
                'bardaana_quantity' => 'required|numeric|min:1',
                'date' => 'nullable|date',
            ];
            
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                return response()->json(['status' => 'error','message' => $validator->errors()->first(),],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
            }
            DB::beginTransaction();
            $purchaseBook = PurchaseBook::findOrFail($request->purchase_book_id);
            $lastBardaanaDetails=$purchaseBook->purchaseBookBardaanaDetails()->latest()->first();
            
            if($lastBardaanaDetails->remaining_bardaana_qty>=$request->bardaana_qty){
                $purchaseBook->purchaseBookBardaanaDetails()->create([
                    'sup_id' => $purchaseBook->sup_id,
                    'bardaana_entry' => $lastBardaanaDetails->bardaana_entry,
                    'bardaana_type' => 'return',
                    'total_bardaana_qty' => $lastBardaanaDetails->total_bardaana_qty,
                    'bardaana_qty' => $request->bardaana_quantity,
                    'remaining_bardaana_qty' => $lastBardaanaDetails->remaining_bardaana_qty-$request->bardaana_quantity,
                    'bardaana_paid_amt' => 0,
                    'date' => $request->input('date', now()),
                    'user_id' => auth()->id(),
                    'season_id' => $purchaseBook->season_id
                ]);

                DB::commit();
                return response()->json(['status' => 'success','message' => 'Bardaana Return Added Successfully.'], Response::HTTP_CREATED); // 201 Created
            }else{
                DB::rollBack();
                return response()->json(['status' => 'error','message' => 'Remaining Bardaana Quantity Is Not Sufficient.'], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error','message' => 'Failed to Add Bardaana Return. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
        }
    }

    public function bardaanaPaidAsCashOrLedger(Request $request){
        $purchase_books = PurchaseBook::with(['product:id,product_name','party:id,person_name','user:'. User::SELECT_BASIC_STRING,'season:id,name'])
        ->whereIn('bardaana_type',['paid_as_cash','paid_as_ledger']);

        if($request->has('start_date') && $request->has('end_date')){
            $startDate = \Carbon\Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->input('end_date'))->endOfDay();

            $purchase_books = $purchase_books->whereBetween('date', [$startDate, $endDate])->get();
            return response()->json(['start_date'=>$startDate,'end_date'=>$endDate,'data' => $purchase_books]);
        }else{
            $purchase_books = $purchase_books->get();
            return response()->json(['data' => $purchase_books]);
        }        
    }

    public function bardaanaReturnList(){
        $purchase_book = PurchaseBook::with(['product:id,product_name','party:id,person_name','user:'. User::SELECT_BASIC_STRING,'season:id,name','purchaseBookBardaanaDetails'])
        ->whereHas('purchaseBookBardaanaDetails', function ($query) {
            $query->where('bardaana_type', 'return')->orderBy('id','desc');
        })->get();
        return response()->json(['data' => $purchase_book]);
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
            $purchase_book = PurchaseBook::with(['product:id,product_name','party:id,person_name','user:'. User::SELECT_BASIC_STRING,'season:id,name','purchaseBookBardaanaDetails'])->findOrFail($id);
            return response()->json(['data' => $purchase_book]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Purchase Order Not Found.'], Response::HTTP_NOT_FOUND);
        }
    }

    public function bardaanaJamaList(){
        $purchase_book = PurchaseBook::with(['product:id,product_name','party:id,person_name','user:'. User::SELECT_BASIC_STRING,'season:id,name','purchaseBookBardaanaDetails'])
        ->whereHas('purchaseBookBardaanaDetails', function ($query) {
            $query->where('remaining_bardaana_qty', '>', 0)->orderBy('id','desc');
        })->get();
        return response()->json(['data' => $purchase_book]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // public function update(Request $request, $id)
    // {
    //     $rules = [
    //         'sup_id' => ['required','exists:customers,id',new ExistsNotSoftDeleted('customers')],
    //         'pro_id' => ['required','exists:products,id',new ExistsNotSoftDeleted('products')],
    //         'bardaana_type' => 'required|in:add,return,paid',
    //         'truck_no' => 'nullable|string|max:50',
    //         'net_weight' => 'required|numeric|min:1',
    //         'khoot' => 'required|numeric|min:0',
    //         'chungi' => 'required|numeric|min:0',
    //         'bardaana_deduction' => 'required|numeric|min:0',
    //         'bardaana_amount' => 'nullable|numeric|min:0',
    //         'bardaana_quantity' => 'required|numeric|min:1',
    //         'freight' => 'required|numeric|min:0',
    //         'price_mann' => 'required|numeric|min:1',
    //         'date' => 'nullable|date',
    //         'payment_type' => 'required|in:cash,cheque,both,online',
    //     ];   
     

    //     if ($request->input('payment_type') == 'cheque') {
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //         $rules['cheque_no']= 'required|string|max:100';
    //         $rules['cheque_date']= 'required|date';
    //         $rules['cheque_amount']= 'required|numeric|min:1';
    //         $rules['bank_tax']= 'required|numeric|min:0';
    //     }else if($request->input('payment_type') == 'cash'){
    //         $rules['cash_amount']= 'required|numeric|min:1';
    //     }else if($request->input('payment_type') == 'online'){
    //         $rules['cash_amount']= ['required','numeric','min:1', 
    //         function ($attribute, $value, $fail) use ($request) {
    //             $bank = Bank::find($request->input('bank_id'));
    //             if ($bank && $value > $bank->balance) {
    //                 $fail('The transection amount cannot be greater than the bank balance.');
    //             }
    //         }];
    //         $rules['transection_id']= 'required|string|max:100';
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //         $rules['bank_tax']= 'required|numeric|min:0';
    //     }else{
    //         $rules['bank_id'] = ['required', 'exists:banks,id', new ExistsNotSoftDeleted('banks')];
    //         $rules['cheque_no']= 'required|string|max:100';
    //         $rules['cheque_date']= 'required|date';
    //         $rules['cheque_amount']= 'required|numeric|min:1';
    //         $rules['bank_tax']= 'required|numeric|min:0';
    //         $rules['cash_amount']= 'required|numeric|min:1';
    //     }

    //     $validator = Validator::make($request->all(), $rules);
        
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $validator->errors()->first(),
    //         ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
    //     }

    //     try {
    //         $payment_type=$request->input('payment_type');
    //         $purchaseBook = PurchaseBook::findOrFail($id);

    //         $supplier=Customer::where(['id'=>$request->sup_id,'customer_type'=>'supplier'])->first();
    //         if(!$supplier){
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Supplier Does Not Exist.',
    //             ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
    //         }

    //         $product=Product::where(['id'=>$request->pro_id])->first();
    //         if(!$product){
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Product Does Not Exist.',
    //             ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Unprocessable Entity
    //         }

    //         // Start a transaction
    //         DB::beginTransaction();
    //         $comp_debit_amt=0;
    //         // calculate final weight,price and weight_per_bag
    //         $final_weight=$request->net_weight-($request->khoot+$request->chungi+$request->bardaana_deduction);
    //         $price = ($request->price_mann * $final_weight) / 40;
    //         $weight_per_bag=$final_weight/$request->bardaana_quantity;
            
    //         $total_amount=$price;
    //         $add_amount=0;
    //         $cash_amount= (($payment_type == 'cash' || $payment_type == 'both' || $payment_type == 'online') && $request->has('cash_amount') && $request->cash_amount>0) ? $request->cash_amount : 0;
    //         $cheque_amount= (($payment_type == 'cheque' || $payment_type == 'both')  && $request->has('cheque_amount') && $request->cheque_amount>0) ? $request->cheque_amount : 0;

    //         $lastLedger = $supplier->ledgers()
    //         ->where('id', '<', function ($query) use ($id) {
    //             $query->select('id')->from('customer_ledgers')->where('book_id', $id)->orderBy('id', 'desc')->limit(1);
    //         })->orderBy('id', 'desc')->first(); 
    //         $currentLedger = $supplier->ledgers()->where('book_id', $id)->first();
           
    //         $previousBalance=0.00;
    //         if($lastLedger){
    //             $previousBalance=$lastLedger->balance;
    //         }

    //         $add_amount+= ($cash_amount+$cheque_amount);

    //         $totalWithPreBlnc=$previousBalance+$total_amount;
    //         $rem_amount=$total_amount-$add_amount;
    //         $rem_blnc_amount=$totalWithPreBlnc-$add_amount;

    //         $purchaseBook->update([
    //             'sup_id' => $request->sup_id,
    //             'pro_id' => $request->pro_id,
    //             'bardaana_type' => $request->bardaana_type,
    //             'truck_no' => $request->truck_no,
    //             'net_weight' => $request->net_weight,
    //             'khoot' => $request->khoot,
    //             'chungi' => $request->chungi,
    //             'bardaana_deduction' => $request->bardaana_deduction,
    //             'bardaana_amount' => $request->filled('bardaana_amount')??0,
    //             'final_weight' => $final_weight,
    //             'bardaana_quantity' => $request->bardaana_quantity,
    //             'weight_per_bag' => $weight_per_bag,
    //             'freight' => $request->freight,
    //             'price' => $price,
    //             'price_mann' => $request->price_mann,
    //             'bank_tax' => $request->bank_tax,
    //             'date' => $request->input('date', now()),
    //             'payment_type' => $request->payment_type,
    //             'bank_id' => $request->bank_id,
    //             'cash_amount' => $cash_amount,
    //             'cheque_amount' => $cheque_amount,
    //             'cheque_no' => $request->cheque_no,
    //             'cheque_date' => $request->cheque_date,
    //             'transection_id' => $payment_type=='online'?$request->transection_id:null,
    //             'net_amount' => $add_amount,
    //             'total_amount' => $total_amount,
    //             'rem_amount' => $rem_amount,
    //         ]);

    //         if (!$purchaseBook) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Failed to Update Purchase Order.',
    //             ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //         }

    //         $transactionData=['id'=>$currentLedger->id,'model_name'=>'App\Models\CustomerLedger','bank_id'=>null,'description'=>null,'dr_amount'=>$total_amount,'cr_amount'=>$add_amount,
    //         'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>$request->payment_type,'cheque_amount'=>0.00,
    //         'cheque_no'=>null,'cheque_date'=>null,'transection_id'=>null,'customer_type'=>'supplier','book_id'=>$purchaseBook->id,'entry_type'=>'dr&cr','balance'=>$rem_blnc_amount];
            
    //         if ($request->input('payment_type') == 'cheque') {
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['cheque_no']= $request->cheque_no;
    //             $transactionData['cheque_date']= $request->cheque_date;
    //             $transactionData['cheque_amount']= $cheque_amount;
    //             $transactionData['bank_tax']= $request->bank_tax;
    //         }else if($request->input('payment_type') == 'cash'){
    //             $comp_debit_amt+=$cash_amount;
    //             $transactionData['cash_amount']= $cash_amount;
    //         }else if($request->input('payment_type') == 'online'){
    //             $transactionData['cash_amount']= $cash_amount;;
    //             $transactionData['transection_id']= $request->transection_id;
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['bank_tax']= $request->bank_tax;
    //         }else{
    //             $comp_debit_amt+=$cash_amount;
    //             $transactionData['bank_id'] = $request->bank_id;
    //             $transactionData['cheque_no']= $request->cheque_no;
    //             $transactionData['cheque_date']= $request->cheque_date;
    //             $transactionData['cheque_amount']= $cheque_amount;
    //             $transactionData['cash_amount']= $cash_amount;
    //             $transactionData['bank_tax']= $request->bank_tax;
    //         }

    //         $res=$purchaseBook->updateTransaction($transactionData);
    //         if($res->original['status']!='success'){
    //             DB::rollBack();
    //             return response()->json($res->original, Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //         }

    //         //company ledger update
    //         $company_ledger=CompanyLedger::where('link_id',$id)->where('link_name','purchase')->first();
    //         if($company_ledger){
    //             if($purchaseBook->payment_type=="cash" || $purchaseBook->payment_type=="both"){
    //                 $transactionDataComp=['id'=>$company_ledger->id,'dr_amount'=>$comp_debit_amt,'cr_amount'=>0.00,'description'=>$request->description];
    //                 $purchaseBook->updateCompanyTransaction($transactionDataComp);
    //             }else{
    //                 $purchaseBook->deleteCompanyTransection($company_ledger->id);
    //             }
    //         }else{
    //             if($purchaseBook->payment_type=="cash" || $purchaseBook->payment_type=="both"){
    //                 $transactionDataComp=['dr_amount'=>$comp_debit_amt,'cr_amount'=>0.00,'description'=>$request->description,'entry_type'=>'dr','link_id'=>$purchaseBook->id,'link_name'=>'purchase'];
    //                 $res=$purchaseBook->addCompanyTransaction($transactionDataComp);
    //                 if(!$res){
    //                     DB::rollBack();
    //                     return response()->json(['status' => 'error','message' => 'Something Went Wrong Please Try Again Later.'], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
    //                 }
    //             }
    //         }

    //         // Commit the transaction
    //         DB::commit();
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Purchase Order Updated Successfully.',
    //         ], Response::HTTP_CREATED); // 201 Created
    //     } catch (ModelNotFoundException $e) {
    //         DB::rollBack();
    //         return response()->json(['status'=>'error', 'message' => 'Purchase Order Found.'], Response::HTTP_NOT_FOUND);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['status'=>'error','message' => 'Failed to Update Purchase Order. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $resource = PurchaseBook::findOrFail($id);
            $customer_ledger=CustomerLedger::where('book_id',$resource->id)->first();
            $company_ledger=CompanyLedger::where('link_id',$resource->id)->where('link_name','purchase')->first();
            $resource->delete();
            $resource->deleteCompanyTransection($company_ledger->id);
            $resource->deleteTransection($customer_ledger->id);
            return response()->json(['status'=>'success','message' => 'Purchase Order Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Purchase Order Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
