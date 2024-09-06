<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CustomerLedgerTrait;
use App\Traits\CompanyLedgerTrait;

class PurchaseBook extends Model
{
    use HasFactory,CustomerLedgerTrait,CompanyLedgerTrait;
    public $table="purchase_book";

    protected $fillable = [
       'serial_no', 'sup_id','pro_id', 'bank_id','bardaana_type','truck_no','net_weight','khoot','chungi','bardaana_deduction','bardaana_amount','final_weight','bardaana_quantity','weight_per_bag',
       'freight','price','price_mann','bank_tax','total_amount','date','payment_type','cash_amount','cheque_amount','cheque_no','cheque_date','transection_id','net_amount','rem_amount'
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
