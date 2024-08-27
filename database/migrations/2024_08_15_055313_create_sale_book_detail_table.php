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
            // $table->foreign('sale_book_id')->references('id')->on('sale_book')->onDelete('cascade');
            $table->unsignedBigInteger('pro_id');
            $table->foreign('pro_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('product_name',100);
            $table->text('product_description')->nullable();         
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->enum('order_status',['cart','completed'])->default('cart');
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
