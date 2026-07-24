<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreditsService
{
    /**
     * Credit cost for a create_review source key.
     */
    public function costForSource(string $source): int
    {
        $costs = config('billing.costs', []);

        return (int) ($costs[$source] ?? 1);
    }

    /**
     * @param  array<string, bool>  $sources  keys: images|capture_url|pdf|html
     */
    public function costForSources(array $sources): int
    {
        $key = array_key_first(array_filter($sources));

        return $key ? $this->costForSource($key) : 1;
    }

    /**
     * @return array<string, int>
     */
    public function burnTable(): array
    {
        return array_map('intval', config('billing.costs', []));
    }

    /**
     * Plan credit pack size (Try monthly grant, or Plus monthly grant when pricing is on).
     */
    public function planGrant(Workspace $workspace): int
    {
        $plan = $workspace->normalizedPlan();

        return (int) config("billing.plans.{$plan}.credits", config('billing.plans.free.credits'));
    }

    /**
     * @deprecated Use planGrant()
     */
    public function monthlyGrant(Workspace $workspace): int
    {
        return $this->planGrant($workspace);
    }

    public function planRenews(Workspace $workspace): bool
    {
        $plan = $workspace->normalizedPlan();

        return (bool) config("billing.plans.{$plan}.renews", $plan === Workspace::PLAN_PRO);
    }

    /**
     * Ensure the workspace has an initial grant; refill only if the plan renews.
     */
    public function ensurePeriod(Workspace $workspace): Workspace
    {
        $workspace->refresh();

        if ($workspace->credits_period_start === null) {
            return $this->grantPeriod($workspace);
        }

        if ($this->planRenews($workspace) && $workspace->credits_period_start->lte(now()->subMonth())) {
            return $this->grantPeriod($workspace);
        }

        return $workspace;
    }

    public function grantPeriod(Workspace $workspace): Workspace
    {
        $grant = $this->planGrant($workspace);

        $workspace->forceFill([
            'credits_balance' => $grant,
            'credits_period_start' => now(),
        ])->save();

        return $workspace->fresh() ?? $workspace;
    }

    /**
     * Support top-up (does not enable monthly renewal).
     */
    public function addCredits(Workspace $workspace, int $amount): Workspace
    {
        if ($amount <= 0) {
            return $workspace;
        }

        if ($workspace->credits_period_start === null) {
            $workspace->forceFill(['credits_period_start' => now()])->save();
        }

        Workspace::query()->whereKey($workspace->id)->increment('credits_balance', $amount);

        return $workspace->fresh() ?? $workspace;
    }

    public function remaining(Workspace $workspace): int
    {
        return (int) $this->ensurePeriod($workspace)->credits_balance;
    }

    /**
     * @throws InsufficientCreditsException
     */
    public function assertAffordable(Workspace $workspace, int $cost): void
    {
        $workspace = $this->ensurePeriod($workspace);
        $remaining = (int) $workspace->credits_balance;

        if ($remaining < $cost) {
            throw new InsufficientCreditsException($workspace, $cost, $remaining);
        }
    }

    /**
     * Debit credits after a successful affordability check.
     *
     * @throws InsufficientCreditsException
     */
    public function debit(Workspace $workspace, int $cost): void
    {
        if ($cost <= 0) {
            return;
        }

        DB::transaction(function () use ($workspace, $cost): void {
            /** @var Workspace $locked */
            $locked = Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $this->ensurePeriod($locked);
            $locked->refresh();

            if ((int) $locked->credits_balance < $cost) {
                throw new InsufficientCreditsException($locked, $cost, (int) $locked->credits_balance);
            }

            $locked->decrement('credits_balance', $cost);
        });

        $workspace->refresh();
    }

    public function refund(Workspace $workspace, int $cost): void
    {
        if ($cost <= 0) {
            return;
        }

        Workspace::query()->whereKey($workspace->id)->increment('credits_balance', $cost);
        $workspace->refresh();
    }

    /**
     * Activate Plus entitlements and refresh the credit period grant.
     */
    public function activatePro(Workspace $workspace, ?string $billingEmail = null): Workspace
    {
        $workspace->forceFill([
            'plan' => Workspace::PLAN_PRO,
            'billing_email' => $billingEmail ?? $workspace->billing_email,
        ])->save();

        return $this->grantPeriod($workspace->fresh() ?? $workspace);
    }

    /**
     * Downgrade to Try — no immediate grant (leftover balance kept; refills when the plan renews).
     */
    public function activateFree(Workspace $workspace): Workspace
    {
        $workspace->forceFill(['plan' => Workspace::PLAN_FREE])->save();

        return $workspace->fresh() ?? $workspace;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Workspace $workspace): array
    {
        $workspace = $this->ensurePeriod($workspace);
        $plan = $workspace->normalizedPlan();
        $grant = $this->planGrant($workspace);
        $renews = $this->planRenews($workspace);
        $periodStart = $workspace->credits_period_start ?? now();
        $periodEnds = $renews ? $periodStart->copy()->addMonth() : null;

        return [
            'plan' => $plan,
            'plan_name' => config("billing.plans.{$plan}.name", $plan),
            'credits_remaining' => (int) $workspace->credits_balance,
            'credits_grant' => $grant,
            'credits_renew' => $renews,
            'credits_period_start' => $periodStart->toIso8601String(),
            'credits_period_ends_at' => $periodEnds?->toIso8601String(),
            'burn_table' => $this->burnTable(),
            'review_retention_days' => $workspace->reviewRetentionDays(),
            'pro_price_usd' => (int) config('billing.plans.pro.price_usd', 9),
            'pro_credits' => (int) config('billing.plans.pro.credits', 100),
        ];
    }
}
