<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who wrote a comment: the owner, a guest, or the agent. `from_owner`
     * could only tell the first two apart, and agents now reply on marks.
     */
    public function up(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            $table->string('author_kind', 16)->default('guest')->after('from_owner');
        });

        DB::table('annotation_comments')->where('from_owner', true)->update(['author_kind' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('annotation_comments', function (Blueprint $table) {
            $table->dropColumn('author_kind');
        });
    }
};
