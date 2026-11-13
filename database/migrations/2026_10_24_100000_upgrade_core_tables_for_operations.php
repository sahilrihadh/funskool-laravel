<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enums become strings so the order lifecycle / staff roles can grow
        // without another ALTER TABLE each time (allowed values live in App\Enums).
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('customer')->change();
            $table->boolean('marketing_opt_in')->default(false)->after('phone');
            $table->timestamp('marketing_opt_in_at')->nullable()->after('marketing_opt_in');
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
            $table->string('payment_status', 30)->default('pending')->change();
            $table->foreignId('sales_channel_id')->nullable()->after('user_id');
            $table->string('external_reference')->nullable()->after('sales_channel_id');
            $table->string('idempotency_key')->nullable()->unique()->after('external_reference');
            $table->string('customer_name')->nullable()->after('idempotency_key');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone', 30)->nullable()->after('customer_email');
            $table->string('currency', 3)->default('INR')->after('customer_phone');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('total');
            $table->string('return_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unique(['sales_channel_id', 'external_reference']);
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->string('status', 30)->change();
            $table->foreignId('user_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->boolean('is_customer_visible')->default(true)->after('note');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('per_customer_limit')->nullable()->after('usage_limit');
            $table->boolean('first_order_only')->default(false)->after('per_customer_limit');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['per_customer_limit', 'first_order_only']);
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('is_customer_visible');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['sales_channel_id', 'external_reference']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'sales_channel_id', 'external_reference', 'idempotency_key', 'customer_name',
                'customer_email', 'customer_phone', 'currency', 'refunded_amount', 'return_url',
                'paid_at', 'cancelled_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'marketing_opt_in', 'marketing_opt_in_at',
                'app_authentication_secret', 'app_authentication_recovery_codes',
            ]);
        });
    }
};
