<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The element a mark (or a guest's suggestion) is on, as the page described
 * it when it was captured: selector, kind, text, box and styles. A snapshot,
 * so it reads the same after the shot's element map is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->json('target')->nullable()->after('area');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->json('target')->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropColumn('target');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn('target');
        });
    }
};
