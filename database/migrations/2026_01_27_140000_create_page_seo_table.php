<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_seo', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 50)->unique();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_title_ar', 70)->nullable();
            $table->string('meta_description', 200)->nullable();
            $table->string('meta_description_ar', 200)->nullable();
            $table->text('keywords')->nullable();
            $table->text('keywords_ar')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_seo');
    }
};
