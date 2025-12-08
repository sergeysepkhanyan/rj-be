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
        Schema::create('user_booking_subservice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bookable_id');
            $table->string('bookable_type');
            $table->index(['bookable_id', 'bookable_type']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_booking_subservice_items');
    }
};
