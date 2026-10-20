<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOverduesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('overdues', function (Blueprint $table) {
            $table->bigIncrements('overdue_id');
            $table->string('overdue_invoice_number')->nullable();
            $table->string('overdue_amount')->nullable();
            $table->string('overdue_status')->nullable();
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
        Schema::dropIfExists('overdues');
    }
}
