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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('main_image')->nullable();
            $table->text('description')->nullable();
            $table->integer('max_quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency')->nullable()->default('AED');
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->decimal('discount')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount_amount')->nullable();
            $table->enum('status', ['active', 'draft'])->default('draft');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
