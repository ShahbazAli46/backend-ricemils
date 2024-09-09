<?php 

namespace App\Traits;

use App\Models\CompanyLedger;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

trait CompanyLedgerTrait
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
   
    public function addCompanyTransaction($tranData)
    {
        $lastLedger = CompanyLedger::orderBy('id', 'desc')->first();
        $previousBalance=0.00;
        if($lastLedger){
            $previousBalance=$lastLedger->balance;
        }
        $current_balance=($previousBalance+$tranData['cr_amount'])-$tranData['dr_amount'];
        $tranData['balance']=$current_balance;
        CompanyLedger::create($tranData);
        return 1;
    }
    

    public function reCalculateCompanyTranBlnc($startingLedgerId,$previousBalance)
    {
        // Get all transactions for company after the specified ledger entry
        $transactions = CompanyLedger::where('id', '>', $startingLedgerId)->orderBy('id', 'asc')->get();
        foreach ($transactions as $transaction) {
            $newBalance = $previousBalance + ($transaction->dr_amount - $transaction->cr_amount);
            $transaction->balance = $newBalance;
            $transaction->save();
            $previousBalance = $newBalance;
        }
    }

    public function deleteCompanyTransection($tran_id){    
        try {
            $resource = CompanyLedger::findOrFail($tran_id);
            $lastLedger = CompanyLedger::where('id', '<', $tran_id)->orderBy('id', 'desc')->first();
            $resource->delete();
            $resource->reCalculateCompanyTranBlnc($lastLedger->id,$lastLedger->balance);
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
    public function updateCompanyTransaction($tranData)
    {
        try {
            $company_ledger = CompanyLedger::findOrFail($tranData['id']);
            $company_ledger->update($tranData);
            $this->reCalculateCompanyTranBlnc($company_ledger->id,$company_ledger->balance);
            return response()->json(['status'=>'success','message' => 'Ledger Updated Successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status'=>'error', 'message' => 'Ledger Not Found.'], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return response()->json(['status'=>'error', 'message' => 'Something went wrong.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } 
    }
}
