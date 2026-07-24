<?php

use App\Models\Workspace;
use App\Services\CreditsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-shot: free workspaces stuck at 0 under the old one-time Try pack
     * get a fresh monthly grant when we switch to rolling credits.
     */
    public function up(): void
    {
        $credits = app(CreditsService::class);

        Workspace::query()
            ->where('plan', Workspace::PLAN_FREE)
            ->where('credits_balance', '<=', 0)
            ->orderBy('id')
            ->each(fn (Workspace $workspace) => $credits->grantPeriod($workspace));
    }

    public function down(): void
    {
        //
    }
};
