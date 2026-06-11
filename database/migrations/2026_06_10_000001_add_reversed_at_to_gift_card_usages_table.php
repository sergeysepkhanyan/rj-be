<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_usages', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_usages', function (Blueprint $table) {
            $table->dropColumn('reversed_at');
        });
    }
};
