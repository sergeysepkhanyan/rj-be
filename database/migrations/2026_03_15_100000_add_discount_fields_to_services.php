<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_services', function (Blueprint $table) {
            $table->boolean('discount')->default(false)->after('vat_enabled');
            $table->string('discount_type')->nullable()->after('discount');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_type');
        });

        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->boolean('discount')->default(false)->after('vat_enabled');
            $table->string('discount_type')->nullable()->after('discount');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('sub_services', function (Blueprint $table) {
            $table->dropColumn(['discount', 'discount_type', 'discount_amount']);
        });

        Schema::table('sub_service_items', function (Blueprint $table) {
            $table->dropColumn(['discount', 'discount_type', 'discount_amount']);
        });
    }
};
