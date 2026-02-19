<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove unique constraints on email and mobile to allow
     * masters to use the same email/mobile as admins.
     * Uniqueness is now enforced at the application level per role type.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropUnique(['mobile']);
        });

        // Add regular indexes for performance (not unique)
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
            $table->index('mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['mobile']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->unique('mobile');
        });
    }
};
