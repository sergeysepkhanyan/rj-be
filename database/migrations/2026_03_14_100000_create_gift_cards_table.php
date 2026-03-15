<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('AED');
            $table->string('image')->nullable();
            $table->enum('status', ['active', 'draft'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gift_card_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 20)->unique();
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->string('currency', 10)->default('AED');
            $table->enum('status', ['active', 'used', 'expired'])->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('gift_card_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_purchase_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_used', 10, 2);
            $table->string('used_for')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_usages');
        Schema::dropIfExists('gift_card_purchases');
        Schema::dropIfExists('gift_cards');
    }
};
