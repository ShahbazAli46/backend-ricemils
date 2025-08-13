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
       'bardaana_qty','remaining_bardaana_qty','bardaana_paid_amt','date','user_id','season_id',
       'total_bardaana_qty_tora','bardaana_qty_tora','remaining_bardaana_qty_tora','total_bardaana_qty_bori',
       'bardaana_qty_bori','remaining_bardaana_qty_bori',
    ];

    public function purchaseBook()
    {
        return $this->belongsTo(PurchaseBook::class, 'purchase_book_id');
    }

}
