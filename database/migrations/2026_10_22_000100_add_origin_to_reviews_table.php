<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a review was started, and when an agent took one made on the
     * site. Agent-made reviews leave both null.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('origin', 16)->nullable()->after('type');
            $table->timestamp('handed_off_at')->nullable()->after('origin');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['origin', 'handed_off_at']);
        });
    }
};
