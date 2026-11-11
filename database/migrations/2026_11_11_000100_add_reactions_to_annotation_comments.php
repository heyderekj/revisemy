<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            $table->json('reactions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            $table->dropColumn('reactions');
        });
    }
};
