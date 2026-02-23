<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_records', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation: User, Booking, Order, Product
            $table->morphs('syncable');

            // Zoho module name: Contacts, Deals, invoices, items
            $table->string('module');

            // The ID of the record in Zoho
            $table->string('zoho_id');

            // Last successful sync time
            $table->timestamp('synced_at')->nullable();

            // Last error message if sync failed
            $table->text('last_error')->nullable();

            $table->timestamps();

            // One Zoho record per entity per module
            $table->unique(['syncable_type', 'syncable_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_records');
    }
};
