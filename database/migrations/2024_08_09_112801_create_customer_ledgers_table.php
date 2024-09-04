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
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->decimal('dr_amount', 15, 2)->default(0.00);
            $table->decimal('cr_amount', 15, 2)->default(0.00);
            $table->decimal('adv_amount', 15, 2)->default(0.00);
            $table->decimal('cash_amount', 15, 2)->default(0.00);
            $table->enum('payment_type',['cash','cheque','both','online'])->nullable();
            $table->decimal('cheque_amount', 15, 2)->default(0.00);
            $table->string('cheque_no',100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->enum('customer_type',['supplier','buyer']);
            $table->unsignedBigInteger('book_id')->nullable();
            $table->enum('entry_type',['dr','cr','adv','dr&cr']);
            $table->decimal('balance', 15, 2)->default(0.00);
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
        Schema::dropIfExists('customer_ledgers');
    }
};
