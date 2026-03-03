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
        Schema::table('sub_services', function (Blueprint $table) {
            $table->boolean('show_duration')->default(true)->after('duration_unit');
        });

        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->boolean('show_duration')->default(true)->after('duration_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_services', function (Blueprint $table) {
            $table->dropColumn('show_duration');
        });

        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->dropColumn('show_duration');
        });
    }
};
