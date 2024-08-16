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
        Schema::dropIfExists('sale_book');
    }
};
