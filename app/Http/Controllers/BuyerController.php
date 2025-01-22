<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Rules\ExistsNotSoftDeleted;
use Illuminate\Support\Facades\DB;

class BuyerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $customers=Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','buyer')->get();
        return response()->json(['data' => $customers]);
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
            'person_name' => 'required|string|max:100',
            'refference_id' => ['nullable','exists:customers,id',new ExistsNotSoftDeleted('customers')],
            'contact' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:100',
            'firm_name' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric',
            'description' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            DB::beginTransaction();
            $openingBalance = is_numeric($request->opening_balance)?$request->opening_balance:0.00;
            $customer = Customer::create([
                'person_name' => $request->input('person_name'),
                'refference_id' => $request->input('refference_id'),
                'contact' => $request->input('contact'),
                'address' => $request->input('address'),
                'firm_name' => $request->input('firm_name'),
                'opening_balance' => $openingBalance, // Default to 0.00 if not provided
                'description' => $request->input('description'),
                'customer_type' => 'buyer'
            ]);


            //if Opening Balance is pos+ then we will receiveable
            //if Opening Balance is neg- then we will pay to buyer
            $transactionData=['customer_id'=>$customer->id,'bank_id'=>null,'description'=>'Opening Balance','dr_amount'=>0.00,'cr_amount'=>0.00,'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>'cash','cheque_amount'=>0.00,'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'buyer','book_id'=>null,'balance'=>$openingBalance];
            if($openingBalance>=1){
                $transactionData['dr_amount']=$openingBalance;
                $transactionData['entry_type']='op';
            }else{
                $transactionData['cr_amount']=abs($openingBalance);
                $transactionData['entry_type']='op';
            }

            $res=$customer->addTransaction($transactionData);
            if(!$res){
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Something Went Wrong Please Try Again Later.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR); // 500 Internal Server Error
            }
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Buyer Created Successfully.',
            ], Response::HTTP_CREATED); // 201 Created
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to Create Buyer. ' . $e->getMessage(),
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
            $customer = Customer::with(['reference:id,person_name,customer_type'])->where('customer_type','buyer')->where('id',$id)->firstOrFail();
            return response()->json(['data' => $customer]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Buyer Not Found.'], Response::HTTP_NOT_FOUND);
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
            'person_name' => 'required|string|max:100',
            'refference_id' => ['nullable','exists:customers,id'],
            'contact' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:100',
            'firm_name' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
            'description' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ],Response::HTTP_UNPROCESSABLE_ENTITY);// 422 Unprocessable Entity
        }
        
        try {
            DB::beginTransaction();
             // Find the customer by ID
            $customer = Customer::where('customer_type','buyer')->where('id',$id)->firstOrFail();
            $update_arr=[
                'person_name' => $request->input('person_name'),
                'refference_id' => $request->input('refference_id'),
                'contact' => $request->input('contact'),
                'address' => $request->input('address'),
                'firm_name' => $request->input('firm_name'),
                'description' => $request->input('description')
            ];
            
            if($request->filled('opening_balance') && $customer->opening_balance!=$request->opening_balance){
                $openingBalance = is_numeric($request->opening_balance)?$request->opening_balance:0.00;
                $update_arr['opening_balance'] = $openingBalance;

                $opening_ledger=$customer->ledgers()->where('description','Opening Balance')->first();
                if($opening_ledger){
                    //update opening balance transection in ledger
                    $transactionData=['id'=>$opening_ledger->id,'bank_id'=>null,'description'=>'Opening Balance','dr_amount'=>0.00,'cr_amount'=>0.00,'adv_amount'=>0.00,'cash_amount'=>0.00,'payment_type'=>'cash','cheque_amount'=>0.00,'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'buyer','book_id'=>null,'balance'=>$openingBalance];
                    if($openingBalance>=1){
                        $transactionData['dr_amount']=$openingBalance;
                        $transactionData['entry_type']='dr';
                    }else{
                        $transactionData['cr_amount']=$openingBalance;
                        $transactionData['cash_amount']=$openingBalance;
                        $transactionData['entry_type']='cr';
                    }
                    $res=$customer->updateTransaction($transactionData);
                }
            }

            $customer->update($update_arr);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Buyer Updated Successfully.',
            ], Response::HTTP_OK); // 200 OK
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['status'=>'error', 'message' => 'Buyer Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status'=>'error','message' => 'Failed to Update Buyer. ' . $e->getMessage(),], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $resource = Customer::where('customer_type','buyer')->where('id',$id)->firstOrFail();
            $resource->delete();
            return response()->json(['status'=>'success','message' => 'Buyer Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Buyer Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
