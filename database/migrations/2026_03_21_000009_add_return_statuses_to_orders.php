<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending', 'pending_payment', 'processing', 'shipped', 'paid', 'refunded', 'cancelled', 'fulfilled', 'gift', 'return_requested', 'return_approved', 'return_rejected') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending', 'pending_payment', 'processing', 'shipped', 'paid', 'refunded', 'cancelled', 'fulfilled', 'gift') DEFAULT 'pending'");
    }
};
