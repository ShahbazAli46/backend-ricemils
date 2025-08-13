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
        Schema::table('purchase_book_bardaana_details', function (Blueprint $table) {
            $table->integer('total_bardaana_qty_tora')->nullable();
            $table->integer('bardaana_qty_tora')->nullable();
            $table->integer('remaining_bardaana_qty_tora')->nullable();

            $table->integer('total_bardaana_qty_bori')->nullable();
            $table->integer('bardaana_qty_bori')->nullable();
            $table->integer('remaining_bardaana_qty_bori')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_book_bardaana_details', function (Blueprint $table) {
            $table->dropColumn(['total_bardaana_qty_tora', 'bardaana_qty_tora', 'remaining_bardaana_qty_tora', 'total_bardaana_qty_bori', 'bardaana_qty_bori', 'remaining_bardaana_qty_bori']);
        });
    }
};
