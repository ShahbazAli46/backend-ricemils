<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CustomerLedgerTrait;

class PurchaseBook extends Model
{
    use HasFactory,CustomerLedgerTrait;
    public $table="purchase_book";

    protected $fillable = [
       'serial_no', 'sup_id','pro_id', 'bank_id','quantity','price','truck_no','packing_type',
        'date','total_amount','payment_type','first_weight','second_weight','net_weight','packing_weight','final_weight',
        'cash_amount','cheque_amount','cheque_no','cheque_date','rem_amount'
    ];

    // Define the relationship with the Product model
    public function product()
    {
        return $this->belongsTo(Product::class, 'pro_id');
    }

    // Define the relationship with the Supplier (Customer) model
    public function supplier()
    {
        return $this->belongsTo(Customer::class, 'sup_id');
    }

    public function ledger()
    {
        return $this->hasOne(CustomerLedger::class, 'book_id');
    }

    //add custom purchase book id according to id
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($purchase_book) {
            if (empty($purchase_book->serial_no) && $purchase_book->id > 0) {
                $purchase_book->serial_no = 'PB-' . str_pad($purchase_book->id, 5, '0', STR_PAD_LEFT);
                $purchase_book->save();
            }
        });
    }
}
