<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('booking', 'ecommerce', 'gift_card') DEFAULT 'booking'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('booking', 'ecommerce') DEFAULT 'booking'");
    }
};
