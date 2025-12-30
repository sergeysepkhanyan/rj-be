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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->nullOnDelete();
            $table->string('provider');
            $table->enum('flow', ['redirect', 'token_charge'])->default('redirect');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->nullable()->default('AED');
            $table->string('external_id')->nullable();
            $table->string('session_id')->nullable();
            $table->enum('status', ['created', 'pending', 'authorized', 'paid', 'failed', 'cancelled', 'expired'])->default('created');
            $table->string('checkout_url')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->index(['provider', 'external_id']);
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
