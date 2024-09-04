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
        Schema::table('customer_ledgers', function (Blueprint $table) {
            $table->string('transection_id',100)->nullable()->after('cheque_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_ledgers', function (Blueprint $table) {
            $table->dropColumn('transection_id');
        });
    }
};
