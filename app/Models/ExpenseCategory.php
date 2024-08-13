<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory,SoftDeletes;
    public $table="expense_categories";

    protected $fillable = ['expense_category'];

    public function payments()
    {
        return $this->hasMany(PaymentFlow::class);
    }
}
