<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('temporary_password_hash')->nullable()->after('is_temporary_password');
            $table->timestamp('temporary_password_used_at')->nullable()->after('temporary_password_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['temporary_password_hash', 'temporary_password_used_at']);
        });
    }
};
