<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleBookDetail extends Model
{
    use HasFactory;
    public $table="sale_book_detail";

    protected $fillable = [
       'sale_book_id','pro_id','packing_id','pro_stock_id','product_name','product_description',
       'packing_size','packing_unit','quantity','price','total_amount'
    ];

    public function saleBook()
    {
        return $this->belongsTo(SaleBook::class);
    }
}
