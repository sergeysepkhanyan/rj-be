<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `status` ENUM('created', 'pending', 'authorized', 'paid', 'failed', 'cancelled', 'expired', 'refunded') NOT NULL DEFAULT 'created'");
    }

    public function down(): void
    {
        DB::statement("UPDATE `payments` SET `status` = 'cancelled' WHERE `status` = 'refunded'");
        DB::statement("ALTER TABLE `payments` MODIFY COLUMN `status` ENUM('created', 'pending', 'authorized', 'paid', 'failed', 'cancelled', 'expired') NOT NULL DEFAULT 'created'");
    }
};
