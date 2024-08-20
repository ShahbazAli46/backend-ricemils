<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvanceCheque extends Model
{
    use HasFactory;
    public $table="advance_cheques";

    protected $fillable = [
        'customer_id','bank_id','description','cheque_amount','cheque_no','cheque_date','customer_type','is_deferred'
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

}
