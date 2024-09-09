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
        Schema::create('company_ledgers', function (Blueprint $table) {
            $table->id();
            $table->decimal('dr_amount', 15, 2)->default(0.00);
            $table->decimal('cr_amount', 15, 2)->default(0.00);
            $table->string('description')->nullable();
            $table->enum('entry_type',['dr','cr']);
            $table->unsignedBigInteger('link_id')->nullable();
            $table->enum('link_name',['purchase','supplier_ledger','expense','buyer_ledger','opening_balance'])->nullable();;
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
        Schema::dropIfExists('company_ledgers');
    }
};
