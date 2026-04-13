<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referral_reward_services', function (Blueprint $table) {
            $table->foreignId('sub_service_id')->nullable()->change();
            if (!Schema::hasColumn('referral_reward_services', 'sub_service_item_id')) {
                $table->foreignId('sub_service_item_id')
                    ->nullable()
                    ->after('sub_service_id')
                    ->constrained('sub_service_items')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('referral_reward_services', function (Blueprint $table) {
            if (Schema::hasColumn('referral_reward_services', 'sub_service_item_id')) {
                $table->dropConstrainedForeignId('sub_service_item_id');
            }
            $table->foreignId('sub_service_id')->nullable(false)->change();
        });
    }
};
