<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_status', ['ordered', 'out_of_delivery', 'delivered', 'canceled'])
                ->nullable()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_status', ['ordered', 'out_for_delivery', 'arriving', 'delivered'])
                ->nullable()
                ->after('status');
        });
    }
};
