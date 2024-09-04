<?php

namespace App\Models;

use App\Traits\CompanyLedgerTrait;
use App\Traits\CustomerLedgerTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory,CompanyLedgerTrait,CustomerLedgerTrait;
    public $table="expenses";

    protected $fillable = [
         'bank_id','expense_category_id','total_amount','payment_type',
         'cash_amount','cheque_amount','cheque_no','cheque_date','transection_id','description'
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

