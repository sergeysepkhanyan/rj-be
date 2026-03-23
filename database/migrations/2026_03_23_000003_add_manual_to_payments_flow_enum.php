<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `payments` MODIFY `flow` ENUM('redirect', 'token_charge', 'manual') DEFAULT 'redirect'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `payments` MODIFY `flow` ENUM('redirect', 'token_charge') DEFAULT 'redirect'");
    }
};
