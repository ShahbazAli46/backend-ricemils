<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE bank_ledgers 
        MODIFY link_name ENUM('expense','party_ledger','opening_balance','investor_ledger','bank_to_cash_self','product_expense','bank_to_bank_self') 
        NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE bank_ledgers 
        MODIFY link_name ENUM('expense','party_ledger','opening_balance','investor_ledger','bank_to_cash_self','product_expense') 
        NULL");
    }
};
