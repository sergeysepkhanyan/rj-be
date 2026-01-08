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
        Schema::table('booking_services', function (Blueprint $table) {
            $table->unsignedBigInteger('master_id')->nullable()->after('booking_id');
            $table->date('date')->nullable()->after('master_id');
            $table->string('timezone', 64)->nullable()->after('date');
            $table->time('start_time')->nullable()->after('timezone');
            $table->time('end_time')->nullable()->after('start_time');
            $table->foreign('master_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['master_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropIndex(['master_id', 'date']);
            $table->dropColumn(['master_id', 'date', 'timezone', 'start_time', 'end_time']);
        });
    }
};
