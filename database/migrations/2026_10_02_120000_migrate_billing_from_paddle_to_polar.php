<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cashier Paddle tables, child tables first. */
    protected const CASHIER_TABLES = ['subscription_items', 'transactions', 'subscriptions', 'customers'];

    public function up(): void
    {
        // Paid pricing was paused under Paddle, so these should be empty. Only
        // drop a table that is — a row here is a real payment record, and
        // losing it silently is worse than leaving an unused table behind.
        foreach (self::CASHIER_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->exists()) {
                Log::warning("Kept Cashier Paddle table {$table}: it has rows.");

                continue;
            }

            Schema::drop($table);
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedInteger('purchased_credits')->default(0)->after('credits_balance');
            $table->string('polar_customer_id')->nullable()->index();
            $table->string('polar_subscription_id')->nullable()->index();
            $table->string('polar_subscription_status')->nullable();
            $table->timestamp('polar_current_period_end')->nullable();
            $table->boolean('polar_cancel_at_period_end')->default(false);
        });

        Schema::create('billing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('polar_order_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // plus | pack
            $table->string('product_key')->nullable();
            $table->string('billing_reason')->nullable();
            $table->unsignedInteger('credits_granted')->default(0);
            $table->unsignedInteger('amount_cents')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_orders');

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex(['polar_customer_id']);
            $table->dropIndex(['polar_subscription_id']);
            $table->dropColumn([
                'purchased_credits',
                'polar_customer_id',
                'polar_subscription_id',
                'polar_subscription_status',
                'polar_current_period_end',
                'polar_cancel_at_period_end',
            ]);
        });
    }
};
