<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop addresses whose order no longer exists (orphans left behind by
        // deleted orders) so the foreign key can be added cleanly.
        DB::table('addresses')
            ->whereNotNull('order_id')
            ->whereNotIn('order_id', function ($q) {
                $q->select('id')->from('orders');
            })
            ->delete();

        Schema::table('addresses', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
    }
};
