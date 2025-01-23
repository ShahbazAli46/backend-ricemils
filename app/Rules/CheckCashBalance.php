<?php

namespace App\Rules;

use App\Models\CompanyLedger;
use Illuminate\Contracts\Validation\Rule;

class CheckCashBalance implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @param int $companyId
     */
    public function __construct()
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Fetch the last ledger entry for the company
        $lastLedger = CompanyLedger::orderBy('id', 'desc')->first();
        $previousBalance = $lastLedger ? $lastLedger->balance : 0.00;
        return $value <= $previousBalance;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The cash amount cannot be greater than the available company balance.';
    }
}
