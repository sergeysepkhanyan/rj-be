<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_account')->default(false)->after('password');
            $table->enum('customer_status', ['lead', 'client'])->default('lead')->after('has_account');
            $table->boolean('contact_declined')->default(false)->after('customer_status');
            $table->boolean('marketing_opt_in')->default(false)->after('contact_declined');
            $table->timestamp('marketing_opt_in_at')->nullable()->after('marketing_opt_in');
            $table->string('unsubscribe_token', 64)->nullable()->after('marketing_opt_in_at');
            $table->timestamp('first_transacted_at')->nullable()->after('unsubscribe_token');

            $table->index('customer_status');
            $table->index('has_account');
            $table->unique('unsubscribe_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_index');
            $table->unique('email');
        });

        DB::table('users')->whereNotNull('password')->update(['has_account' => true]);

        $transactedUserIds = collect();

        $transactedUserIds = $transactedUserIds->merge(
            DB::table('bookings')
                ->whereNotNull('user_id')
                ->whereIn('payment_status', ['paid', 'refunded', 'gift'])
                ->distinct()
                ->pluck('user_id')
        );

        $transactedUserIds = $transactedUserIds->merge(
            DB::table('orders')
                ->whereNotNull('user_id')
                ->whereIn('status', ['paid', 'refunded', 'fulfilled', 'shipped', 'processing'])
                ->distinct()
                ->pluck('user_id')
        );

        $transactedUserIds = $transactedUserIds->unique()->filter()->values();

        if ($transactedUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $transactedUserIds)
                ->update([
                    'customer_status' => 'client',
                    'first_transacted_at' => DB::raw('COALESCE(first_transacted_at, created_at)'),
                ]);
        }

        $tokenlessIds = DB::table('users')->whereNull('unsubscribe_token')->pluck('id');
        foreach ($tokenlessIds as $id) {
            DB::table('users')->where('id', $id)->update([
                'unsubscribe_token' => Str::random(48),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->index('email', 'users_email_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_unsubscribe_token_unique');
            $table->dropIndex('users_customer_status_index');
            $table->dropIndex('users_has_account_index');
            $table->dropColumn([
                'has_account',
                'customer_status',
                'contact_declined',
                'marketing_opt_in',
                'marketing_opt_in_at',
                'unsubscribe_token',
                'first_transacted_at',
            ]);
        });
    }
};
