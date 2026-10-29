<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A workspace given Plus without paying (the studio's own, a friend,
     * support). Set and cleared by `revisemy:comp`. Comped Plus refills on
     * its own rolling month, since Polar never bills it.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('comped_at')->nullable()->after('plan');
            $table->string('comp_note', 160)->nullable()->after('comped_at');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['comped_at', 'comp_note']);
        });
    }
};
