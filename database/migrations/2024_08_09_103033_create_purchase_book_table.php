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
        Schema::create('purchase_book', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no',50)->nullable();
            $table->unsignedBigInteger('sup_id');
            $table->foreign('sup_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('pro_id');
            $table->foreign('pro_id')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->decimal('price', 15, 2)->default(0.00);
            $table->string('truck_no',50)->nullable();
            $table->enum('packing_type',['add','return','paid']);
            $table->date('date')->default(DB::raw('CURRENT_DATE'));
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->enum('payment_type',['cash','cheque','both']);
            $table->decimal('cash_amount', 15, 2)->default(0.00);
            $table->decimal('cheque_amount', 15, 2)->default(0.00);
            $table->string('cheque_no',100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('rem_amount', 15, 2)->default(0.00);
            $table->integer('first_weight')->default(0);
            $table->integer('second_weight')->default(0);
            $table->integer('net_weight')->default(0);
            $table->integer('packing_weight')->default(0);
            $table->integer('final_weight')->default(0);
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
        Schema::dropIfExists('purchase_book');
    }
};
