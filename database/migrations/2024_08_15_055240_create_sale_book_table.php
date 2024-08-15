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
        Schema::create('sale_book', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no',50)->nullable();
            $table->unsignedBigInteger('buyer_id');
            $table->foreign('buyer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->string('truck_no',50)->nullable();
            $table->date('date')->default(DB::raw('CURRENT_DATE'));
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('cascade');
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->enum('payment_type',['cash','cheque','both']);
            $table->decimal('cash_amount', 15, 2)->default(0.00);
            $table->decimal('cheque_amount', 15, 2)->default(0.00);
            $table->string('cheque_no',100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('rem_amount', 15, 2)->default(0.00);
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
        Schema::dropIfExists('sale_book');
    }
};
