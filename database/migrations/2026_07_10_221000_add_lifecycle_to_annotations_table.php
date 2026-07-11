<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->string('status')->default('open')->index()->after('body');
            $table->text('resolution_note')->nullable()->after('status');
            $table->foreignId('after_screenshot_id')->nullable()->after('resolution_note')->constrained('screenshots')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('after_screenshot_id');
            $table->timestamp('verified_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('after_screenshot_id');
            $table->dropColumn(['status', 'resolution_note', 'resolved_at', 'verified_at']);
        });
    }
};
