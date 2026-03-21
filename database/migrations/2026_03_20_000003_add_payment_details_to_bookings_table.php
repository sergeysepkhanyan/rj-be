<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('paid_payment_method')->nullable()->after('payment_status');
            $table->string('gift_card_code')->nullable()->after('paid_payment_method');
            $table->decimal('tip_amount', 10, 2)->default(0)->after('gift_card_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['paid_payment_method', 'gift_card_code', 'tip_amount']);
        });
    }
};
