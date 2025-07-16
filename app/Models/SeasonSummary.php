<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeasonSummary extends Model
{
    use HasFactory;
    public $table="season_summaries";

    protected $fillable = [
       'name','description','sale_amount','purchase_amount','expense_amount'
    ];
}
