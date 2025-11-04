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
        Schema::create('user_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('master_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('sub_service_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('time');
            $table->decimal('discount')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount_amount')->nullable();
            $table->enum('payment_type', ['Pay Now', 'Pay Later'])->default('Pay Now');
            $table->decimal('payment_amount')->nullable()->default(0);
            $table->string('payment_currency')->nullable();
            $table->string('payment_status')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_bookings');
    }
};
