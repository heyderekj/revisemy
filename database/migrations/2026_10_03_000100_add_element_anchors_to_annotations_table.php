<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            // The page element a mark was snapped to: {selector, tag, kind, text}.
            $table->json('element')->nullable();
            // Where that element turned up in the next pass's capture:
            // {screenshot_id, area, text, looks_live} or {missing: true}.
            $table->json('carried')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropColumn(['element', 'carried']);
        });
    }
};
