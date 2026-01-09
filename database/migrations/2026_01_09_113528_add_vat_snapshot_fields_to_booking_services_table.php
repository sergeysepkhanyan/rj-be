<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->default(0)->after('price');
            $table->boolean('vat_enabled')->default(false)->after('base_price');
            $table->decimal('vat_rate', 5, 4)->default(0.0500)->after('vat_enabled');
            $table->decimal('vat_amount', 10, 2)->default(0)->after('vat_rate');
            $table->decimal('final_price', 10, 2)->default(0)->after('vat_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'vat_enabled', 'vat_rate', 'vat_amount', 'final_price']);
        });
    }
};
