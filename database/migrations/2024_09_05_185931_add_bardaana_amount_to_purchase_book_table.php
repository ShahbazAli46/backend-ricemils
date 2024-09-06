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
        Schema::table('purchase_book', function (Blueprint $table) {
            $table->decimal('bardaana_amount')->default(0.00)->after('bardaana_deduction');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_book', function (Blueprint $table) {
            $table->dropColumn('bardaana_amount');
        });
    }
};
