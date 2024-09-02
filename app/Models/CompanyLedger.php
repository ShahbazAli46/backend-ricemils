<?php

namespace App\Models;

use App\Traits\CompanyLedgerTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyLedger extends Model
{
    use HasFactory,CompanyLedgerTrait;
    public $table="company_ledgers";

    protected $fillable = [
        'dr_amount', 'cr_amount', 'description', 'entry_type', 'link_id', 'link_name', 'balance'
    ];
}
