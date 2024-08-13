<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Packing extends Model
{
    use HasFactory,SoftDeletes;
    public $table="packings";

    protected $fillable = [
        'packing_size', 'packing_unit'
    ];

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class);
    }
}