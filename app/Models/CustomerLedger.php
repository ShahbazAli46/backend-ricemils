<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CustomerLedgerTrait;

class CustomerLedger extends Model
{
    use HasFactory,CustomerLedgerTrait;
    public $table="customer_ledgers";

    protected $fillable = [
        'customer_id','bank_id','description','dr_amount','cr_amount','cash_amount','payment_type','cheque_amount','cheque_no','cheque_date','customer_type','book_id','entry_type','balance'
    ];

    // Define the relationship with the customer
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function purchaseBook()
    {
        return $this->belongsTo(PurchaseBook::class, 'book_id');
    }
}
