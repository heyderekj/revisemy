<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Who wrote a comment (owner, guest or the agent), and who last moved a
 * mark's status (owner or agent), so the agent's work can be labelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            $table->string('author_type', 8)->default('owner')->after('author');
        });

        DB::table('annotation_comments')->where('from_owner', false)->update(['author_type' => 'guest']);

        Schema::table('annotations', function (Blueprint $table) {
            $table->string('status_by', 8)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            $table->dropColumn('author_type');
        });

        Schema::table('annotations', function (Blueprint $table) {
            $table->dropColumn('status_by');
        });
    }
};
