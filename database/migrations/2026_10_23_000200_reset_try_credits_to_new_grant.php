<?php

use App\Models\Workspace;
use App\Services\CreditsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-shot: when Try went from 20 to 40 credits a month, every free
     * workspace starts a fresh month at the new grant. Anyone already above
     * it (support top-ups) keeps their balance; pack credits are untouched.
     */
    public function up(): void
    {
        $credits = app(CreditsService::class);
        $grant = (int) config('billing.plans.free.credits', 40);

        Workspace::query()
            ->where('plan', Workspace::PLAN_FREE)
            ->where('credits_balance', '<', $grant)
            ->orderBy('id')
            ->each(fn (Workspace $workspace) => $credits->grantPeriod($workspace));
    }

    public function down(): void
    {
        //
    }
};
