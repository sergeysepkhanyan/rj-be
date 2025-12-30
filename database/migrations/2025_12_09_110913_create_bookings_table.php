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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('master_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('type', ['booking', 'break'])
                ->default('booking');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('duration')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('discount_type', ['none', 'percent', 'fixed'])
                ->default('none');
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->string('discount_label')->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->enum('payment_mode', ['pay_now', 'pay_later'])
                ->default('pay_later');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])
                ->default('unpaid');
            $table->enum('status', ['pending_payment','pending', 'confirmed', 'completed', 'cancelled'])
                ->default('confirmed');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['master_id', 'date', 'start_time', 'end_time']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
