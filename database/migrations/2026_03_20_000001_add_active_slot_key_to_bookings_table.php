<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('active_slot_key')->nullable()->after('end_time');
            $table->unique('active_slot_key', 'bookings_active_slot_key_unique');
        });

        // Backfill existing non-cancelled bookings
        DB::table('bookings')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('master_id')
            ->whereNull('active_slot_key')
            ->orderBy('id')
            ->each(function ($booking) {
                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update([
                        'active_slot_key' => "{$booking->master_id}_{$booking->date}_{$booking->start_time}_{$booking->end_time}",
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_active_slot_key_unique');
            $table->dropColumn('active_slot_key');
        });
    }
};
