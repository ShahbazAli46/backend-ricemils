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
        Schema::create('company_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->decimal('total_weight', 15, 2)->default(0.00);
            $table->decimal('stock_in', 15, 2)->default(0.00);
            $table->decimal('stock_out', 15, 2)->default(0.00);
            $table->decimal('remaining_weight', 15, 2)->default(0.00);
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('linkable_type',100)->nullable();
            $table->enum('entry_type',['sale','purchase','opening'])->nullable();;
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('price_mann', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
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
        Schema::dropIfExists('company_product_stocks');
    }
};
