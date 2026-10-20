<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Laravel 7 migrations didn't match what the code writes: `transcations.discount`
 * was added to the production database by hand, and several columns the controllers
 * leave empty were NOT NULL. Guarded so it is safe on an imported production database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transcations', 'discount')) {
            Schema::table('transcations', function (Blueprint $table) {
                $table->integer('discount')->nullable();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
            $table->text('description')->nullable()->change();
        });

        Schema::table('history_products', function (Blueprint $table) {
            $table->string('costprice')->nullable()->change();
            $table->string('customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        //
    }
};
