<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['annotations', 'findings', 'annotation_comments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->json('element_references')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['annotations', 'findings', 'annotation_comments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('element_references'));
        }
    }
};
