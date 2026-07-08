<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complimentary_rewards', function (Blueprint $table) {
            // A reward can now be for a sub-service OR a specific sub-service item,
            // mirroring referral_reward_services — so sub_service_id becomes nullable.
            $table->foreignId('sub_service_id')->nullable()->change();

            if (!Schema::hasColumn('complimentary_rewards', 'sub_service_item_id')) {
                $table->foreignId('sub_service_item_id')
                    ->nullable()
                    ->after('sub_service_id')
                    ->constrained('sub_service_items')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('complimentary_rewards', function (Blueprint $table) {
            if (Schema::hasColumn('complimentary_rewards', 'sub_service_item_id')) {
                $table->dropConstrainedForeignId('sub_service_item_id');
            }
            $table->foreignId('sub_service_id')->nullable(false)->change();
        });
    }
};
