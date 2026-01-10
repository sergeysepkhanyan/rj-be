<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('master_id')->nullable()->change();
            $table->foreign('master_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('master_id')->nullable(false)->change();
            $table->foreign('master_id')
                ->references('id')
                ->on('users');
        });
    }
};
