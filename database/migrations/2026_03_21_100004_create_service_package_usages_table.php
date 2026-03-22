<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_package_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_package_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('used_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service_package_purchase_id', 'service_package_item_id'], 'spu_purchase_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_package_usages');
    }
};
