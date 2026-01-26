<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('manual_referral_id')
                ->nullable()
                ->after('referral_id')
                ->constrained('referrals')
                ->nullOnDelete()
                ->comment('Manually assigned discount tier by admin, bypasses visit count requirements');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manual_referral_id']);
            $table->dropColumn('manual_referral_id');
        });
    }
};
