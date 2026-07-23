<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->text('suggested_copy')->nullable()->after('body');
            $table->text('question_answer')->nullable()->after('suggested_copy');
            $table->string('source', 32)->default('human')->after('question_answer');
            $table->foreignId('promoted_from_finding_id')
                ->nullable()
                ->after('source')
                ->constrained('findings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('annotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promoted_from_finding_id');
            $table->dropColumn(['suggested_copy', 'question_answer', 'source']);
        });
    }
};
