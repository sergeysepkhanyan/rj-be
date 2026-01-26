<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->upSqlite();
        } else {
            Schema::table('addresses', function (Blueprint $table) {
                $table->unsignedBigInteger('country_id')->nullable()->after('city');
            });

            $this->migrateCountryData();

            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('country');
                $table->foreign('country_id')->references('id')->on('countries')->onDelete('restrict');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->downSqlite();
        } else {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropForeign(['country_id']);
                $table->string('country', 100)->nullable()->after('city');
            });

            $this->rollbackCountryData();

            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('country_id');
            });
        }
    }

    /**
     * Migrate country string to country_id
     */
    protected function migrateCountryData(): void
    {
        DB::statement('
            UPDATE addresses 
            SET country_id = (
                SELECT id FROM countries 
                WHERE countries.name = addresses.country 
                LIMIT 1
            ) 
            WHERE country IS NOT NULL
        ');
    }

    /**
     * Rollback country_id to country string
     */
    protected function rollbackCountryData(): void
    {
        DB::statement('
            UPDATE addresses 
            SET country = (
                SELECT name FROM countries 
                WHERE countries.id = addresses.country_id 
                LIMIT 1
            ) 
            WHERE country_id IS NOT NULL
        ');
    }

    /**
     * Handle SQLite migration
     */
    protected function upSqlite(): void
    {
        if (!Schema::hasTable('addresses')) {
            return;
        }

        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('city');
        });

        $this->migrateCountryData();

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('country');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('restrict');
        });
    }

    /**
     * Handle SQLite rollback
     */
    protected function downSqlite(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->string('country', 100)->nullable()->after('city');
        });

        $this->rollbackCountryData();

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('country_id');
        });
    }
};
