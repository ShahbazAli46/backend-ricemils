<?php 

namespace App\Traits;

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
    // $transactionData=['customer_id'=>$customer->id,'bank_id'=>null,'description'=>'Opening Balance','dr_amount'=>0.00,
    // 'cr_amount'=>$openingBalance,'adv_amount'=>0.00,'cash_amount'=>$openingBalance,'payment_type'=>'cash','cheque_amount'=>0.00,'cheque_no'=>null,'cheque_date'=>null,'customer_type'=>'supplier','book_id'=>null,'entry_type'=>'cr'];

    public function addTransaction($tranData)
    {
        // Find the customer by ID
        $customer = Customer::find($tranData['customer_id']);
        if (!$customer) {
            // throw new \Exception("Customer not found");
            return 0;
        }
        CustomerLedger::create($tranData);
        $this->reCalculateCrntBlnc($tranData['customer_id']);
        return 1;
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

    public function deleteTransection($tran_id){    
        try {
            $resource = CustomerLedger::findOrFail($tran_id);
            $customer_id=$resource->customer_id;
            $ledgerId=$resource->id;
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
}
