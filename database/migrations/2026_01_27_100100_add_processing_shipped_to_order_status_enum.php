<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'pending_payment',
                'processing',
                'shipped',
                'paid',
                'refunded',
                'cancelled',
                'fulfilled'
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'pending_payment',
                'paid',
                'refunded',
                'cancelled',
                'fulfilled'
            ])->default('pending')->change();
        });
    }
};
