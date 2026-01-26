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
            Schema::table('orders', function (Blueprint $table) {
                $table->string('orderable_type')->nullable()->change();
                $table->unsignedBigInteger('orderable_id')->nullable()->change();
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

        Schema::table('orders', function (Blueprint $table) {
            $table->string('orderable_type')->nullable(false)->change();
            $table->unsignedBigInteger('orderable_id')->nullable(false)->change();
        });
    }

    /**
     * Handle SQLite migration by recreating table
     */
    protected function upSqlite(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        $hasDeliveryStatus = Schema::hasColumn('orders', 'delivery_status');

        $columns = [
            'id', 'user_id', 'type', 'orderable_type', 'orderable_id',
            'amount', 'currency', 'status', 'reference', 'meta',
            'paid_at', 'cancelled_at', 'refunded_at',
            'created_at', 'updated_at'
        ];

        if ($hasDeliveryStatus) {
            $columns[] = 'delivery_status';
            $columns[] = 'delivery_status_updated_at';
        }

        $columnsString = implode(', ', $columns);
        $selectColumns = $columnsString;

        $deliveryStatusColumns = $hasDeliveryStatus
            ? ",\n                delivery_status VARCHAR(255),\n                delivery_status_updated_at DATETIME"
            : '';

        DB::statement("
            CREATE TABLE orders_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                type VARCHAR(255) DEFAULT 'booking',
                orderable_type VARCHAR(255),
                orderable_id INTEGER,
                amount DECIMAL(10,2),
                currency VARCHAR(255) DEFAULT 'AED',
                status VARCHAR(255) DEFAULT 'pending',
                reference VARCHAR(255),
                meta TEXT,
                paid_at DATETIME,
                cancelled_at DATETIME,
                refunded_at DATETIME{$deliveryStatusColumns},
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        DB::statement("INSERT INTO orders_new ({$columnsString}) SELECT {$selectColumns} FROM orders");
        DB::statement('DROP TABLE orders');
        DB::statement('ALTER TABLE orders_new RENAME TO orders');
        DB::statement('CREATE UNIQUE INDEX orders_reference_unique ON orders(reference)');
    }
};
