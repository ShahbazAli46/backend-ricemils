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
        Schema::table('company_product_stocks', function (Blueprint $table) {
            $table->decimal('bardaana_amt')->default(0.00)->after('price_mann');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('company_product_stocks', function (Blueprint $table) {
            $table->dropColumn(['bardaana_amt']);
        });
    }
};
