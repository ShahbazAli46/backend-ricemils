<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory,SoftDeletes;
    public $table="products";

    protected $fillable = [
        'product_name', 'product_description', 'product_type'
    ];

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    // Define the relationship with the table provided
    public function purchaseBooks()
    {
        return $this->hasMany(PurchaseBook::class, 'pro_id');
    }
}
