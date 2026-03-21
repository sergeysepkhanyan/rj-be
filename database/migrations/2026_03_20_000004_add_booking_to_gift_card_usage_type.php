<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `gift_card_usages` MODIFY COLUMN `used_for_type` ENUM('service', 'product', 'booking') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `gift_card_usages` MODIFY COLUMN `used_for_type` ENUM('service', 'product') NULL");
    }
};
