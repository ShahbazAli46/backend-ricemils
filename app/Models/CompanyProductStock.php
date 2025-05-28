<?php

namespace App\Models;

use App\Traits\CompanyLedgerTrait;
use App\Traits\CustomerLedgerTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProductStock extends Model
{
    use HasFactory,CompanyLedgerTrait,CustomerLedgerTrait;
    public $table="company_product_stocks";

    protected $fillable = [
        'product_id', 'total_weight', 'stock_in', 'stock_out','remaining_weight', 'linkable_id', 'linkable_type','entry_type','price','price_mann',
        'total_amount','balance','party_id','description','expense_entry_type','user_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function linkable()
    {
        return $this->morphTo();
    }

    public function party()
    {
        return $this->belongsTo(Customer::class, 'party_id','');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
