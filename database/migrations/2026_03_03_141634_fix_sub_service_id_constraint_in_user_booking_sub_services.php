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
        Schema::table('user_booking_sub_services', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['sub_service_id']);

            // Make the column nullable
            $table->unsignedBigInteger('sub_service_id')->nullable()->change();

            // Add new foreign key with nullOnDelete
            $table->foreign('sub_service_id')
                  ->references('id')
                  ->on('sub_services')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_booking_sub_services', function (Blueprint $table) {
            // Drop the modified foreign key
            $table->dropForeign(['sub_service_id']);

            // Restore original constraint
            $table->unsignedBigInteger('sub_service_id')->nullable(false)->change();

            $table->foreign('sub_service_id')
                  ->references('id')
                  ->on('sub_services');
        });
    }
};
