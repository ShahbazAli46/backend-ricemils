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
        Schema::create('sale_book_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_book_id');
            $table->foreign('sale_book_id')->references('id')->on('sale_book')->onDelete('cascade');
            $table->unsignedBigInteger('pro_id');
            $table->foreign('pro_id')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedBigInteger('packing_id');
            $table->foreign('packing_id')->references('id')->on('packings')->onDelete('cascade');
            $table->unsignedBigInteger('pro_stock_id')->nullable();
            $table->foreign('pro_stock_id')->references('id')->on('product_stocks')->onDelete('set null');
            $table->string('product_name',100);
            $table->text('product_description')->nullable();
            $table->string('packing_size',100);
            $table->string('packing_unit',50)->default('KG');
            $table->string('quantity',50);
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
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
        Schema::dropIfExists('sale_book_detail');
    }
};
