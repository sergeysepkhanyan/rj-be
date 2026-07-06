<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `status` ENUM('pending_payment', 'pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'confirmed'");
    }

    public function down(): void
    {
        DB::statement("UPDATE `bookings` SET `status` = 'cancelled' WHERE `status` = 'no_show'");
        DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `status` ENUM('pending_payment', 'pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'confirmed'");
    }
};
