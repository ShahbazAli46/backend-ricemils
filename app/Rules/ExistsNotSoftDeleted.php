<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;
class ExistsNotSoftDeleted implements Rule
{
    protected $table;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($table)
    {
        $this->table = $table;
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
        // Check if the record exists and the deleted_at column is null
        return DB::table($this->table)
            ->where('id', $value)
            ->whereNull('deleted_at') // Check if the soft delete column is null
            ->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The selected :attribute does not exist or has been deleted.';
    }
}
