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
                $table->string('country', 100)->nullable()->after('city');
                $table->string('zip_code')->nullable()->change();
            });

            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('state');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('state')->after('city');
            $table->string('zip_code')->nullable(false)->change();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }

    /**
     * Handle SQLite migration by recreating table
     */
    protected function upSqlite(): void
    {
        if (!Schema::hasTable('addresses')) {
            return;
        }

        $columns = [
            'id', 'user_id', 'order_id', 'type', 'is_default',
            'name', 'last_name', 'mobile', 'address', 'additional_address',
            'city', 'zip_code', 'deleted_at', 'created_at', 'updated_at'
        ];

        $columnsString = implode(', ', $columns);

        DB::statement("
            CREATE TABLE addresses_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                order_id INTEGER,
                type VARCHAR(255),
                is_default INTEGER DEFAULT 0,
                name VARCHAR(255),
                last_name VARCHAR(255),
                mobile VARCHAR(255),
                address VARCHAR(255),
                additional_address VARCHAR(100),
                city VARCHAR(255),
                country VARCHAR(100),
                zip_code VARCHAR(255),
                deleted_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        DB::statement("INSERT INTO addresses_new ({$columnsString}) SELECT {$columnsString} FROM addresses");
        DB::statement('DROP TABLE addresses');
        DB::statement('ALTER TABLE addresses_new RENAME TO addresses');
    }
};
