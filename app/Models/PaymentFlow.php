<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentFlow extends Model
{
    use HasFactory;
    public $table="payments_flow";

    protected $fillable = [
        'customer_id', 'bank_id','expense_category_id','payment_type','cheque_no','cheque_date','description','amount','payment_flow_type'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }
}
