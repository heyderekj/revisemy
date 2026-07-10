<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screenshot_id')->constrained()->cascadeOnDelete();
            $table->decimal('x', 8, 6);
            $table->decimal('y', 8, 6);
            $table->string('severity')->nullable();
            $table->text('body');
            $table->unsignedInteger('number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotations');
    }
};
