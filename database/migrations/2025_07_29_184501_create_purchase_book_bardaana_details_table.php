<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('purchase_book_bardaana_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_book_id');
            $table->foreign('purchase_book_id')->references('id')->on('purchase_book')->onDelete('cascade');
            $table->unsignedBigInteger('sup_id');
            $table->foreign('sup_id')->references('id')->on('customers')->onDelete('cascade');
            $table->string('bardaana_entry',50)->nullable();
            $table->string('bardaana_type',50)->default('add');
            $table->integer('total_bardaana_qty')->default(0);
            $table->integer('bardaana_qty')->default(0);
            $table->integer('remaining_bardaana_qty')->default(0);
            $table->decimal('bardaana_paid_amt')->default(0.00);
            $table->date('date')->default(DB::raw('CURRENT_DATE'));
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('season_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchase_book_bardaana_details');
    }
};
