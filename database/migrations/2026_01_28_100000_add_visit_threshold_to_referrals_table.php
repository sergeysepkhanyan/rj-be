<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->unsignedInteger('visit_threshold')->nullable()->after('value')->comment('Minimum visits required to qualify for this discount tier');
            $table->boolean('enabled')->default(true)->after('visit_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['visit_threshold', 'enabled']);
        });
    }
};
