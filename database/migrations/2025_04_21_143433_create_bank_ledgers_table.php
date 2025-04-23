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
        Schema::create('bank_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_id');
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->decimal('dr_amount', 15, 2)->default(0.00);
            $table->decimal('cr_amount', 15, 2)->default(0.00);
            $table->enum('entry_type',['dr','cr','op']);
            $table->enum('payment_type',['cheque','online'])->nullable();
            $table->decimal('cheque_amount', 15, 2)->default(0.00);
            $table->string('cheque_no',100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('online_amount', 15, 2)->default(0.00);
            $table->string('transection_id',100)->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('linkable_type',100)->nullable();
            $table->enum('link_name',['expense','party_ledger','opening_balance','investor_ledger','bank_to_cash_self','product_expense'])->nullable();
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
        Schema::dropIfExists('bank_ledgers');
    }
};
