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
            $table->index(['status', 'payment_mode', 'payment_status', 'expires_at'], 'idx_bookings_expire_pending');
            $table->index(['status', 'payment_mode', 'created_at'], 'idx_bookings_expire_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_expire_pending');
            $table->dropIndex('idx_bookings_expire_created');
        });
    }
};
