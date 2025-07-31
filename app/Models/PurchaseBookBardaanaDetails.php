<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseBookBardaanaDetails extends Model
{
    use HasFactory;
    public $table="purchase_book_bardaana_details";

    protected $fillable = [
       'purchase_book_id', 'sup_id','bardaana_entry', 'bardaana_type','total_bardaana_qty',
       'bardaana_qty','remaining_bardaana_qty','bardaana_paid_amt','date','user_id','season_id'
    ];

    public function purchaseBook()
    {
        return $this->belongsTo(PurchaseBook::class, 'purchase_book_id');
    }

}
