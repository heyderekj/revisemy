<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which agent took a review made on the site ("Claude"), so its page can
     * say who has it. Null for a try token, or before any agent has.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('handed_off_to', 60)->nullable()->after('handed_off_at');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('handed_off_to');
        });
    }
};
