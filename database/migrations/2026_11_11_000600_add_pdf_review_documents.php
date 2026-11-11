<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('pdf_path')->nullable();
            $table->string('pdf_disk')->nullable();
        });
        foreach (['annotations', 'findings'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->json('pdf_anchor')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn(['pdf_path', 'pdf_disk']));
        foreach (['annotations', 'findings'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('pdf_anchor'));
        }
    }
};
