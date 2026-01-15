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
        Schema::create('booking_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('guest_session_id', 64)->nullable()->index();
            $table->foreignId('master_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->morphs('bookable');
            $table->unsignedInteger('duration_minutes');
            $table->date('date');
            $table->string('timezone', 64)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['user_id', 'master_id', 'date']);
            $table->index(['guest_session_id', 'master_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_selections');
    }
};
