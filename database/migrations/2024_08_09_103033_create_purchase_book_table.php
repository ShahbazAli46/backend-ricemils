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
            $table->enum('bardaana_type',['add','return','paid']);//packing_type
            $table->string('truck_no',50)->nullable();
            $table->decimal('net_weight')->default(0.00);
            $table->decimal('khoot')->default(0.00);
            $table->decimal('chungi')->default(0.00);
            $table->decimal('bardaana_deduction')->default(0.00);
            $table->decimal('final_weight')->default(0.00);
            $table->integer('bardaana_quantity')->default(0);
            $table->decimal('weight_per_bag')->default(0.00);
            $table->decimal('freight', 15, 2)->default(0.00);
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('price_mann', 15, 2)->default(0.00);
            $table->decimal('bank_tax', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->date('date')->default(DB::raw('CURRENT_DATE'));
            $table->enum('payment_type',['cash','cheque','both','online','none']);
            $table->decimal('cash_amount', 15, 2)->default(0.00);
            $table->decimal('cheque_amount', 15, 2)->default(0.00);
            $table->string('cheque_no',100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('net_amount', 15, 2)->default(0.00);
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
        Schema::dropIfExists('purchase_book');
    }
};
