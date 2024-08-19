<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;
    public $table="expenses";

    protected $fillable = [
         'bank_id','expense_category_id','total_amount','payment_type',
         'cash_amount','cheque_amount','cheque_no','cheque_date','description'
    ];

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}

