<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 50)->unique();
            $table->string('email', 254);
            $table->text('question');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('respondent', 40)->nullable();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_requests');
    }
};
