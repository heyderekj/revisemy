<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('page_url')->nullable()->after('context');
        });

        Schema::table('screenshots', function (Blueprint $table) {
            $table->string('second_opinion_status')->default('idle')->after('sort_order');
            $table->text('second_opinion_error')->nullable()->after('second_opinion_status');
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screenshot_id')->constrained()->cascadeOnDelete();
            $table->string('source'); // checklist | openai | agent
            $table->string('severity'); // suggestion | a11y | polish
            $table->text('body');
            $table->json('area')->nullable(); // {x,y,w,h} normalized 0–1
            $table->unsignedInteger('related_pin')->nullable();
            $table->timestamps();

            $table->index(['screenshot_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');

        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropColumn(['second_opinion_status', 'second_opinion_error']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('page_url');
        });
    }
};
