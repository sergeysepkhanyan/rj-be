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
        Schema::create('referral_reward_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_rewards_config_id')->constrained('referral_rewards_config')->onDelete('cascade');
            $table->foreignId('sub_service_id')->constrained('sub_services')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_reward_services');
    }
};
