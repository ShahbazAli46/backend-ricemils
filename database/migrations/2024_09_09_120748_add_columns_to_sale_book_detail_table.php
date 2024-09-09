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
            $table->decimal('khoot')->default(0.00)->after('weight');
            $table->decimal('chungi')->default(0.00)->after('khoot');
            $table->decimal('net_weight')->default(0.00)->after('chungi');
            $table->decimal('salai_amt_per_bag')->default(0.00)->after('net_weight');
            $table->decimal('bardaana_quantity')->default(0.00)->after('salai_amt_per_bag');
            $table->decimal('total_salai_amt')->default(0.00)->after('bardaana_quantity');
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
            $table->dropColumn(['khoot','chungi','net_weight','salai_amt_per_bag','no_of_salai_bags','total_salai_amt']);
        });
    }
};
