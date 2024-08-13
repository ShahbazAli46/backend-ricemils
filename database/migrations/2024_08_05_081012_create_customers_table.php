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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('person_name',100);
            $table->unsignedBigInteger('refference_id')->nullable();
            $table->foreign('refference_id')->references('id')->on('customers')->onDelete('set null');
            $table->string('contact',100)->nullable();
            $table->string('address',100)->nullable();
            $table->string('firm_name',100)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('customers');
    }
};
