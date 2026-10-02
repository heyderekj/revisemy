<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedSmallInteger('webhook_failures')->default(0)->after('webhook_url');
            $table->timestamp('webhook_paused_at')->nullable()->after('webhook_failures');
            $table->string('webhook_last_error', 255)->nullable()->after('webhook_paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['webhook_failures', 'webhook_paused_at', 'webhook_last_error']);
        });
    }
};
