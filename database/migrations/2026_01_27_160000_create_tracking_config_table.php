<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_config', function (Blueprint $table) {
            $table->id();
            $table->string('google_analytics_id', 50)->nullable();
            $table->string('google_tag_manager_id', 50)->nullable();
            $table->string('facebook_pixel_id', 50)->nullable();
            $table->string('snapchat_pixel_id', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_config');
    }
};
