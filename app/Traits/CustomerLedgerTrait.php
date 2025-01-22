<?php 

namespace App\Traits;

use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

trait CustomerLedgerTrait
{
    /**
     * Add a debit or credit amount to the customer's ledger and update the current balance.
     *
     * @param int $customerId
     * @param float $amount
     * @param string $type 'dr' for debit, 'cr' for credit
     * @param array $transactionData (optional) Additional data for the transaction
     * @return void
     */
   
    public function addTransaction($tranData)
    {
        // Find the customer by ID
        $customer = Customer::find($tranData['customer_id']);
        if (!$customer) {
            return 0;
        }
        $res=CustomerLedger::create($tranData);
        $this->reCalculateCrntBlnc($tranData['customer_id']);
        $this->reCalculateBankBalance($tranData);
        return $res;
    }
    
    public function reCalculateBankBalance($tranData){
        if(isset($tranData['id'])){
            $className = $tranData['model_name'];
            $data_row=$className::find($tranData['id']);
            if($data_row->payment_type=='cheque' || $data_row->payment_type=='both' || $data_row->payment_type=='online'){
                $bank=Bank::find($data_row->bank_id);
                if($tranData['customer_type']=='buyer' && ($data_row->entry_type=='cr' || $data_row->entry_type=='dr&cr')){
                    $dec_amount=$data_row->cheque_amount;
                    if($tranData['payment_type']=='online'){
                        $dec_amount=$data_row->cash_amount;
                    }
                    $bank->balance=$bank->balance-$dec_amount;
                    $bank->save();
                }else if($tranData['customer_type']=='supplier' && ($data_row->entry_type=='cr' || $data_row->entry_type=='dr&cr' || $className == 'App\Models\Expense')){
                    $add_amount=$data_row->cheque_amount;
                    if($tranData['payment_type']=='online'){
                        $add_amount=$data_row->cash_amount;
                    }
                    $bank->balance=$bank->balance+$add_amount;
                    $bank->save();
                }else if($tranData['customer_type']=='investor' && ($data_row->entry_type=='cr' || $data_row->entry_type=='dr&cr')){
                    $dec_amount=$data_row->cheque_amount;
                    if($tranData['payment_type']=='online'){
                        $dec_amount=$data_row->cash_amount;
                    }
                    $bank->balance=$bank->balance+$dec_amount;
                    $bank->save();
                }
            }
        }

        if($tranData['customer_type']=='buyer' &&  ($tranData['payment_type']=='cheque' || $tranData['payment_type']=='both' || $tranData['payment_type']=='online') && ($tranData['entry_type']=='cr' || $tranData['entry_type']=='dr' || $tranData['entry_type']=='dr&cr')){
            $bank=Bank::find($tranData['bank_id']);
            $add_amount=$tranData['cheque_amount'];
            if($tranData['payment_type']=='online'){
                $add_amount=$tranData['cash_amount'];
            }
            if($tranData['entry_type']=='cr'){
                $bank->balance=$bank->balance+$add_amount;
            }else{
                $bank->balance=$bank->balance-$add_amount;
            }
            $bank->save();
        }else if($tranData['customer_type']=='supplier' &&  ($tranData['payment_type']=='cheque' || $tranData['payment_type']=='both' || $tranData['payment_type']=='online') && ($tranData['entry_type']=='cr' || $tranData['entry_type']=='dr' || $tranData['entry_type']=='dr&cr')){
            $bank=Bank::find($tranData['bank_id']);
            $dec_amount=$tranData['cheque_amount'];
            if($tranData['payment_type']=='online'){
                $dec_amount=$tranData['cash_amount'];
            }
            if($tranData['entry_type']=='dr'){
                $bank->balance=$bank->balance+$dec_amount;
            }else{
                $bank->balance=$bank->balance-$dec_amount;
            }
            $bank->save();
        }else if($tranData['customer_type']=='investor' &&  ($tranData['payment_type']=='cheque' || $tranData['payment_type']=='both' || $tranData['payment_type']=='online') && ($tranData['entry_type']=='cr' || $tranData['entry_type']=='dr' || $tranData['entry_type']=='dr&cr')){
            $bank=Bank::find($tranData['bank_id']);
            $add_amount=$tranData['cheque_amount'];
            if($tranData['payment_type']=='online'){
                $add_amount=$tranData['cash_amount'];
            }
            if($tranData['entry_type']=='cr'){
                $bank->balance=$bank->balance-$add_amount;
            }else{
                $bank->balance=$bank->balance+$add_amount;
            }
            $bank->save();
        }
    }

    public function reCalculateCrntBlnc($customer_id){
        $customer = Customer::find($customer_id);
        $lastLedger = $customer->ledgers()->orderBy('id', 'desc')->first();
        if ($lastLedger) {
            $customer->current_balance = $lastLedger->balance;
            $customer->save();
        }
    }
    
    public function reCalculateTranBlnc($customerId, $startingLedgerId,$previousBalance)
    {
        // Get all transactions for this customer after the specified ledger entry
        $transactions = CustomerLedger::where('customer_id', $customerId)->where('id', '>', $startingLedgerId)->orderBy('id', 'asc')->get();
        foreach ($transactions as $transaction) {
            $newBalance = $previousBalance + ($transaction->dr_amount - $transaction->cr_amount);
            $transaction->balance = $newBalance;
            $transaction->save();
            $previousBalance = $newBalance;
        }
    }

    /**
     * Update the customer's current balance directly.
     *
     * @param int $customerId
     * @param float $newBalance
     * @return void
     */
    public function updateTransaction($tranData)
    {
        try {
            $customer_ledger = CustomerLedger::findOrFail($tranData['id']);
            $this->reCalculateBankBalance($tranData);

            $tranData['customer_id']=$customer_ledger->customer_id;
            $customer_ledger->update($tranData);
            $this->reCalculateTranBlnc($tranData['customer_id'], $customer_ledger->id,$customer_ledger->balance);
            $this->reCalculateCrntBlnc($tranData['customer_id']);
            return response()->json(['status'=>'success','message' => 'Ledger Updated Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Ledger Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }

    public function deleteTransection($tran_id){    
        try {
            $resource = CustomerLedger::findOrFail($tran_id);
            $customer_id=$resource->customer_id;
            $ledgerId=$resource->id;

            //recalculate bank balance on delete
            if($resource->payment_type=='online' || $resource->payment_type=='cheque' || $resource->payment_type=='both'){
                $bank=Bank::find($resource->bank_id);
                if($resource->customer_type=='buyer' && ($resource->entry_type=='cr' || $resource->entry_type=='dr&cr')){
                    $dec_amount=$resource->cheque_amount;
                    if($resource->payment_type=='online'){
                        $dec_amount=$resource->cash_amount;
                    }
                    $bank->balance=$bank->balance-$dec_amount;
                    $bank->save();
                }else if($resource->customer_type=='supplier' && ($resource->entry_type=='cr' || $resource->entry_type=='dr&cr')){
                    $add_amount=$resource->cheque_amount;
                    if($resource->payment_type=='online'){
                        $add_amount=$resource->cash_amount;
                    }
                    $bank->balance=$bank->balance+$add_amount;
                    $bank->save();
                }
            }

            $lastLedger = CustomerLedger::where('customer_id', $customer_id)->where('id', '<', $ledgerId)->orderBy('id', 'desc')->first();
            $resource->delete();

            $resource->reCalculateTranBlnc($customer_id, $lastLedger->id,$lastLedger->balance);
            $resource->reCalculateCrntBlnc($customer_id);
            return response()->json(['status'=>'success','message' => 'Ledger Deleted Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Ledger Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }

}
