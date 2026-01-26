<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_scripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tracking_config_id');
            $table->string('name', 100);
            $table->text('code');
            $table->enum('position', ['head', 'body_start', 'body_end']);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            
            $table->foreign('tracking_config_id')
                ->references('id')
                ->on('tracking_config')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_scripts');
    }
};
