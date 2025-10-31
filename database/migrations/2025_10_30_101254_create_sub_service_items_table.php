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
        Schema::create('sub_service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['Simple', 'Variant Based'])->default('Simple');
            $table->decimal('price', 8, 2)->default(0)->nullable();
            $table->string('currency')->nullable();
            $table->decimal('duration', 5, 2)->default(0)->nullable();
            $table->string('duration_unit')->nullable()->default('min');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_service_items');
    }
};
