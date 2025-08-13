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
            $table->integer('bardaana_quantity_tora')->nullable()->after('bardaana_quantity');
            $table->integer('bardaana_quantity_bori')->nullable()->after('bardaana_quantity_tora');
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
            $table->dropColumn(['bardaana_quantity_tora', 'bardaana_quantity_bori']);
        });
    }
};
