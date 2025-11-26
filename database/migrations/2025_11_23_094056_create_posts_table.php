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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('lang')->default('en');
            $table->string('author');
            $table->string('title');
            $table->string('meta_title');
            $table->text('meta_description');
            $table->string('slug')->unique();
            $table->text('preview');
            $table->longText('content');
            $table->string('image')->nullable();
            $table->boolean('show_author')->default(false);
            $table->enum('status', ['Draft', 'Published', 'Archived'])->default('Draft');
            $table->date('publish_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
