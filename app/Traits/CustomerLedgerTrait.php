<?php 

namespace App\Traits;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\CustomerTransaction; // Assuming this is the second table

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
        $this->reCalculate($tranData['customer_id']);
        return 1;
    }

    public function reCalculate($customer_id){
        $customer = Customer::find($customer_id);
        $current_blanace=$customer->ledgers()->sum('balance');
        $customer->current_balance=$current_blanace;
        $customer->save();
    }

    /**
     * Update the customer's current balance directly.
     *
     * @param int $customerId
     * @param float $newBalance
     * @return void
     */
    public function updateCurrentBalance($customerId, $newBalance)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            throw new \Exception("Customer not found");
        }

        $customer->current_balance = $newBalance;
        $customer->save();
    }
}
