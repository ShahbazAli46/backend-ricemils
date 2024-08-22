<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory,SoftDeletes;
    public $table="expense_categories";

    protected $fillable = ['expense_category'];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function getExpensesSumTotalAmountAttribute()
    {
        // Return 0.00 if the sum is null
        return $this->attributes['expenses_sum_total_amount'] ?? 0.00;
    }
}
