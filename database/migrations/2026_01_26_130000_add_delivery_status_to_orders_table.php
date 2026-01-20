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
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_status', ['ordered', 'out_for_delivery', 'arriving', 'delivered'])
                ->nullable()
                ->after('status');
            $table->timestamp('delivery_status_updated_at')->nullable()->after('delivery_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'delivery_status_updated_at']);
        });
    }
};
