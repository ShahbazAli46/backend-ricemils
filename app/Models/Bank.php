<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasFactory,SoftDeletes;
    public $table="banks";
    
    protected $fillable = ['bank_name'];

    public function payments()
    {
        return $this->hasMany(PaymentFlow::class);
    }

    public function expense()
    {
        return $this->hasMany(Expense::class);
    }
}
