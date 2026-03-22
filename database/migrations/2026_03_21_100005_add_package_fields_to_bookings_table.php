<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('service_package_purchase_id')
                ->nullable()
                ->after('complimentary_reward_id')
                ->constrained()
                ->nullOnDelete();
            $table->boolean('is_package_booking')->default(false)->after('service_package_purchase_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['service_package_purchase_id']);
            $table->dropColumn(['service_package_purchase_id', 'is_package_booking']);
        });
    }
};
