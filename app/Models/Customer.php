<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\CustomerLedgerTrait;

class Customer extends Model
{
    use HasFactory,SoftDeletes,CustomerLedgerTrait;
    public $table="customers";
    
    protected $fillable = [
        'person_name', 'refference_id', 'contact', 'address', 'firm_name', 'opening_balance', 'description','customer_type','current_balance'
    ];

    // Define the relationship with customer_ledgers
    public function ledgers()
    {
        return $this->hasMany(CustomerLedger::class, 'customer_id');
    }

    public function reference()
    {
        return $this->belongsTo(Customer::class, 'refference_id');
    }

    public function purchaseBooks()
    {
        return $this->hasMany(PurchaseBook::class, 'sup_id');
    }

    public function saleBooks()
    {
        return $this->hasMany(SaleBook::class, 'buyer_id');
    }
    
    public function advanceCheques()
    {
        return $this->hasMany(AdvanceCheque::class);
    }

    /**
     * Get the customers that have this customer as a reference.
     */
    public function referencedCustomers()
    {
        return $this->hasMany(Customer::class, 'refference_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentFlow::class);
    }
}
