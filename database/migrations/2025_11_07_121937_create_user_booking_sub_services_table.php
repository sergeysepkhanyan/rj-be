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
        Schema::create('user_booking_sub_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_service_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_booking_sub_services');
    }
};
