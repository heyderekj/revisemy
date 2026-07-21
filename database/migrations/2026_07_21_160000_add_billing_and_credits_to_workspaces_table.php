<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('plan', 32)->default('free')->after('public_id');
            $table->string('billing_email')->nullable()->after('plan');
            $table->unsignedInteger('credits_balance')->default(0)->after('billing_email');
            $table->timestamp('credits_period_start')->nullable()->after('credits_balance');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'billing_email',
                'credits_balance',
                'credits_period_start',
            ]);
        });
    }
};
