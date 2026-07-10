<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('status')->default('open')->after('related_pin');
            $table->index(['screenshot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropIndex(['screenshot_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
