<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'registration_source')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('registration_source', 32)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'registration_source')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('registration_source');
            });
        }
    }
};
