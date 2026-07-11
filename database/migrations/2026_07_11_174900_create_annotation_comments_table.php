<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annotation_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annotation_id')->constrained()->cascadeOnDelete();
            $table->string('author', 40);
            $table->boolean('from_owner')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->index(['annotation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotation_comments');
    }
};
