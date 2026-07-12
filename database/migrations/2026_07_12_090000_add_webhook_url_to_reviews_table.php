<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Event-driven workflows: POSTed to on human decision, so CI and
            // pipelines can gate on approval instead of polling get_review.
            $table->string('webhook_url', 2048)->nullable()->after('page_url');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('webhook_url');
        });
    }
};
