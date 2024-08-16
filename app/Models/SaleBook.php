<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CustomerLedgerTrait;

class SaleBook extends Model
{
    use HasFactory,CustomerLedgerTrait;
    public $table="sale_book";

    protected $fillable = [
       'ref_no','buyer_id','truck_no', 'bank_id','packing_type','date','total_amount',
    ];

    public function details()
    {
        return $this->hasMany(SaleBookDetail::class,'sale_book_id');
    }

    // Define the relationship with the Product model
    public function product()
    {
        return $this->belongsTo(Product::class, 'pro_id');
    }

    // Define the relationship with the Buyer (Customer) model
    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function ledger()
    {
        return $this->hasOne(CustomerLedger::class, 'book_id');
    }

    //add custom sale book id according to id
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($sale_book) {
            if (empty($sale_book->ref_no) && $sale_book->id > 0) {
                $sale_book->ref_no = 'SB-' . str_pad($sale_book->id, 5, '0', STR_PAD_LEFT);
                $sale_book->save();
            }
        });
    }
}
