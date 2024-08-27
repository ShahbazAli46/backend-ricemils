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
        Schema::table('sale_book_detail', function (Blueprint $table) {
            $table->decimal('price_mann', 15, 2)->default(0.00)->after('price');
            $table->decimal('weight', 15, 2)->default(0.00)->after('product_description');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_book_detail', function (Blueprint $table) {
            $table->dropColumn(['price_mann','weight']);
        });
    }
};
