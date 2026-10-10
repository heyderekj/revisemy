<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A project's own design rules (its DESIGN.md), so the second opinion checks
 * screenshots against them. A review keeps the copy it was made with; a
 * workspace can keep a default for when the agent sends none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->longText('design_rules')->nullable();
            $table->string('design_rules_source', 16)->nullable();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->longText('design_rules')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['design_rules', 'design_rules_source']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('design_rules');
        });
    }
};
