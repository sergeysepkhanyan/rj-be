<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('quantity_threshold')->default(10);
            $table->decimal('discount_percentage', 5, 2)->default(10.00);
            $table->string('discount_label', 100)->default('Bulk Discount');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_settings');
    }
};
