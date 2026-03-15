<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_usages', function (Blueprint $table) {
            $table->enum('used_for_type', ['service', 'product'])->nullable()->after('amount_used');
            $table->unsignedBigInteger('used_for_id')->nullable()->after('used_for_type');
            $table->string('used_for_name')->nullable()->after('used_for_id');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_usages', function (Blueprint $table) {
            $table->dropColumn(['used_for_type', 'used_for_id', 'used_for_name']);
        });
    }
};
