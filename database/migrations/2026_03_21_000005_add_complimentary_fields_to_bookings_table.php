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
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_complimentary')->default(false)->after('active_slot_key');
            $table->foreignId('complimentary_reward_id')->nullable()->after('is_complimentary')
                ->constrained('complimentary_rewards')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['complimentary_reward_id']);
            $table->dropColumn(['is_complimentary', 'complimentary_reward_id']);
        });
    }
};
