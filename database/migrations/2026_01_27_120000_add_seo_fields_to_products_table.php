<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title', 70)->nullable()->after('description_ar');
            $table->string('meta_title_ar', 70)->nullable()->after('meta_title');
            $table->string('meta_description', 200)->nullable()->after('meta_title_ar');
            $table->string('meta_description_ar', 200)->nullable()->after('meta_description');
            $table->string('slug', 255)->nullable()->unique()->after('meta_description_ar');
            $table->string('redirect_url', 500)->nullable()->after('slug');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn([
                'meta_title',
                'meta_title_ar',
                'meta_description',
                'meta_description_ar',
                'slug',
                'redirect_url',
            ]);
        });
    }
};
