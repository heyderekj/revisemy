<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_requests', function (Blueprint $table) {
            $table->ulid('group_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('feedback_requests', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};
