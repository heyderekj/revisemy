<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('assistant_seen_at')->nullable();
            $table->string('assistant_name', 80)->nullable();
            $table->string('assistant_last_tool', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['assistant_seen_at', 'assistant_name', 'assistant_last_tool']);
        });
    }
};
