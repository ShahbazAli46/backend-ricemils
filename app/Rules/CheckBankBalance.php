<?php

namespace App\Rules;

use App\Models\Bank;
use Illuminate\Contracts\Validation\Rule;

class CheckBankBalance implements Rule
{
    protected $bankId;
    protected $amount;

    /**
     * Create a new rule instance.
     *
     * @param int $bankId
     */
    public function __construct($bankId)
    {
        $this->bankId = $bankId;
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
        $bank = Bank::find($this->bankId);

        if (!$bank) {
            return false; // Bank doesn't exist
        }

        return $value <= $bank->balance; // Pass if value <= bank balance
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The cash amount cannot be greater than the bank balance.';
    }
}
