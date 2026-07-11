<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('author', 60)->nullable()->after('source');
            $table->decimal('x', 8, 6)->nullable()->after('body');
            $table->decimal('y', 8, 6)->nullable()->after('x');
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn(['author', 'x', 'y']);
        });
    }
};
