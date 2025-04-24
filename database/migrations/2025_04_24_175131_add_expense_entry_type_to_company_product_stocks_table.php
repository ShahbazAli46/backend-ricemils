<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('company_product_stocks', function (Blueprint $table) {
            $table->string('expense_entry_type',50)->nullable()->after('entry_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('company_product_stocks', function (Blueprint $table) {
            $table->dropColumn(['expense_entry_type']);
        });
    }
};
