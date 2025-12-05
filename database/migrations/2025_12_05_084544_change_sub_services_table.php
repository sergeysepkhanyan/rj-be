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
            $table->enum('type', ['Simple', 'Variant Based'])->default('Simple')->after('service_id');
            $table->decimal('price', 8, 2)->default(0)->nullable()->after('image');
            $table->string('currency')->nullable()->after('price');
            $table->decimal('duration', 5, 2)->default(0)->nullable()->after('currency');
            $table->string('duration_unit')->nullable()->default('min')->after('duration');
        });

        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_services', function (Blueprint $table) {
            $table->dropColumn(['type', 'price', 'currency', 'duration', 'duration_unit']);
        });
        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->enum('type', ['Simple', 'Variant Based'])->default('Simple');
        });
    }
};
