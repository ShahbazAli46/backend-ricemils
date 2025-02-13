<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasFactory,SoftDeletes;
    public $table="banks";
    
    protected $fillable = ['bank_name','balance'];

    public function expense()
    {
        return $this->hasMany(Expense::class);
    }

    public function advanceCheques()
    {
        return $this->hasMany(AdvanceCheque::class);
    }

    public function getAdvanceChequesSumChequeAmountAttribute()
    {
        // Return 0.00 if the sum is null
        return $this->attributes['advance_cheques_sum_cheque_amount'] ?? 0.00;
    }
    
    public function customerLedger()
    {
        return $this->hasMany(CustomerLedger::class);
    }
}
