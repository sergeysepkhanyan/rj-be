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
        Schema::table('user_bookings', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('mobile')->nullable()->after('name');
            $table->string('email')->nullable()->after('mobile');
            $table->text('notes')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_bookings', function (Blueprint $table) {
            //
        });
    }
};
