<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleBookDetail extends Model
{
    use HasFactory;
    public $table="sale_book_detail";

    protected $fillable = [
       'sale_book_id','pro_id','product_name','product_description','weight','price','khoot','chungi','net_weight','salai_amt_per_bag','bardaana_quantity','total_salai_amt','price_mann','total_amount'
    ];

    public function saleBook()
    {
        return $this->belongsTo(SaleBook::class,'sale_book_id');
    }
}
