<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'service_package' to orders.type enum
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `type` ENUM('booking', 'ecommerce', 'gift_card', 'service_package') DEFAULT 'booking'");

        // Add 'package' to bookings.payment_status enum
        DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `payment_status` ENUM('unpaid', 'paid', 'refunded', 'gift', 'package') DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `type` ENUM('booking', 'ecommerce', 'gift_card') DEFAULT 'booking'");
        DB::statement("ALTER TABLE `bookings` MODIFY COLUMN `payment_status` ENUM('unpaid', 'paid', 'refunded', 'gift') DEFAULT 'unpaid'");
    }
};
