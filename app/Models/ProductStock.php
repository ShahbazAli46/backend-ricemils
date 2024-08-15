<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;
    public $table="product_stocks";

    protected $fillable = [
        'product_id', 'packing_id','product_name','product_description','packing_size','packing_unit','quantity'
    ];
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function packing()
    {
        return $this->belongsTo(Packing::class);
    }
}
