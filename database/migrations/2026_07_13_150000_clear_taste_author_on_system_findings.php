<?php

use App\Models\Finding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clear legacy taste-person bylines on system findings. Disclosure moved
     * to the craft chip; author remains for guest suggestion names only.
     */
    public function up(): void
    {
        DB::table('findings')
            ->whereIn('source', [
                Finding::SOURCE_CHECKLIST,
                Finding::SOURCE_OPENAI,
                Finding::SOURCE_ANTHROPIC,
            ])
            ->whereNotNull('author')
            ->update(['author' => null]);
    }

    public function down(): void
    {
        // Irreversible — legacy personal bylines are not restored.
    }
};
