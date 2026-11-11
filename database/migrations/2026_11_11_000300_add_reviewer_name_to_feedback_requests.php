<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_requests', function (Blueprint $table) {
            $table->string('reviewer_name', 40)->nullable();
            $table->string('email', 254)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('feedback_requests')->whereNull('email')->update(['email' => '']);

        Schema::table('feedback_requests', function (Blueprint $table) {
            $table->dropColumn('reviewer_name');
            $table->string('email', 254)->nullable(false)->change();
        });
    }
};
