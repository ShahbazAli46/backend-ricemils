<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankLedger extends Model
{
    use HasFactory;
    public $table="bank_ledgers";
    protected $fillable = ['bank_id','description','dr_amount','cr_amount','entry_type','payment_type','cheque_amount',
    'cheque_no','cheque_date','online_amount','transection_id','linkable_id','linkable_type','link_name','balance','created_at','updated_at'];
    
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function linkable()
    {
        return $this->morphTo();
    }
}
