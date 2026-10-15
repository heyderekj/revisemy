<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A mark's state changes, each with who made it. Comments stay in
     * annotation_comments; the timeline reads both.
     */
    public function up(): void
    {
        Schema::create('annotation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annotation_id')->constrained()->cascadeOnDelete();
            $table->string('actor', 16);
            $table->string('type', 16);
            $table->text('body')->nullable();
            $table->foreignId('after_screenshot_id')->nullable()->constrained('screenshots')->nullOnDelete();
            $table->timestamps();

            $table->index(['annotation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotation_events');
    }
};
