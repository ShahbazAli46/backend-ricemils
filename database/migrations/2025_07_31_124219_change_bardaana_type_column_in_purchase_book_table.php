<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_book', function (Blueprint $table) {
            DB::statement("ALTER TABLE purchase_book MODIFY bardaana_type VARCHAR(100) NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_book', function (Blueprint $table) {
            DB::statement("ALTER TABLE purchase_book MODIFY bardaana_type ENUM('add', 'return', 'paid') NULL");
        });
    }
};
