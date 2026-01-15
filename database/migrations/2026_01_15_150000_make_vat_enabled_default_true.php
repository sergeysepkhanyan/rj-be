<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sub_services')
            ->whereNull('vat_enabled')
            ->orWhere('vat_enabled', false)
            ->update(['vat_enabled' => true]);

        DB::table('sub_service_items')
            ->whereNull('vat_enabled')
            ->orWhere('vat_enabled', false)
            ->update(['vat_enabled' => true]);

        Schema::table('sub_services', function (Blueprint $table) {
            $table->boolean('vat_enabled')->default(true)->change();
        });

        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->boolean('vat_enabled')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_services', function (Blueprint $table) {
            $table->boolean('vat_enabled')->default(false)->change();
        });

        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->boolean('vat_enabled')->default(false)->change();
        });
    }
};
