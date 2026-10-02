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

    public function planRenews(Workspace $workspace): bool
    {
        $plan = $workspace->normalizedPlan();

        return (bool) config("billing.plans.{$plan}.renews", $plan === Workspace::PLAN_PRO);
    }

    /**
     * Ensure the workspace has an initial grant; lazily refill Try monthly.
     * Plus refills when Polar bills the subscription (order.paid webhook), so
     * its refill follows the real billing cycle instead of this clock.
     */
    public function ensurePeriod(Workspace $workspace): Workspace
    {
        $workspace->refresh();

        if ($workspace->credits_period_start === null) {
            return $this->grantPeriod($workspace);
        }

        if ($workspace->normalizedPlan() === Workspace::PLAN_FREE
            && $this->planRenews($workspace)
            && $workspace->credits_period_start->lte(now()->subMonth())) {
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

    /**
     * Purchased pack credits — never reset by a monthly refill.
     */
    public function addPurchasedCredits(Workspace $workspace, int $amount): Workspace
    {
        if ($amount > 0) {
            Workspace::query()->whereKey($workspace->id)->increment('purchased_credits', $amount);
        }

        return $workspace->fresh() ?? $workspace;
    }

    /**
     * Claw back a refunded pack. Clamped at zero: credits already spent stay spent.
     */
    public function removePurchasedCredits(Workspace $workspace, int $amount): Workspace
    {
        if ($amount > 0) {
            DB::transaction(function () use ($workspace, $amount): void {
                $locked = Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
                $locked->forceFill([
                    'purchased_credits' => max(0, (int) $locked->purchased_credits - $amount),
                ])->save();
            });
        }

        return $workspace->fresh() ?? $workspace;
    }

    /** Monthly grant plus purchased credits. */
    public function remaining(Workspace $workspace): int
    {
        return $this->ensurePeriod($workspace)->totalCredits();
    }

    /**
     * @throws InsufficientCreditsException
     */
    public function assertAffordable(Workspace $workspace, int $cost): void
    {
        $workspace = $this->ensurePeriod($workspace);
        $remaining = $workspace->totalCredits();

        if ($remaining < $cost) {
            throw new InsufficientCreditsException($workspace, $cost, $remaining);
        }
    }

    /**
     * Debit credits — the monthly grant first, then purchased credits.
     * Returns the split so a refund can put credits back where they came from.
     *
     * @return array{monthly: int, purchased: int}
     *
     * @throws InsufficientCreditsException
     */
    public function debit(Workspace $workspace, int $cost): array
    {
        if ($cost <= 0) {
            return ['monthly' => 0, 'purchased' => 0];
        }

        $split = DB::transaction(function () use ($workspace, $cost): array {
            /** @var Workspace $locked */
            $locked = Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $this->ensurePeriod($locked);
            $locked->refresh();

            if ($locked->totalCredits() < $cost) {
                throw new InsufficientCreditsException($locked, $cost, $locked->totalCredits());
            }

            $monthly = min($cost, max(0, (int) $locked->credits_balance));
            $purchased = $cost - $monthly;

            $locked->forceFill([
                'credits_balance' => (int) $locked->credits_balance - $monthly,
                'purchased_credits' => (int) $locked->purchased_credits - $purchased,
            ])->save();

            return ['monthly' => $monthly, 'purchased' => $purchased];
        });

        $workspace->refresh();

        return $split;
    }

    /**
     * @param  array{monthly: int, purchased: int}  $split  as returned by debit()
     */
    public function refund(Workspace $workspace, array $split): void
    {
        $monthly = max(0, (int) ($split['monthly'] ?? 0));
        $purchased = max(0, (int) ($split['purchased'] ?? 0));

        if ($monthly + $purchased === 0) {
            return;
        }

        Workspace::query()->whereKey($workspace->id)->update([
            'credits_balance' => DB::raw('credits_balance + '.$monthly),
            'purchased_credits' => DB::raw('purchased_credits + '.$purchased),
        ]);
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
     * Downgrade to Try — no immediate grant (leftover balance kept; Try refills a month after the last grant).
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
        $periodEnds = match (true) {
            ! $renews => null,
            $plan === Workspace::PLAN_PRO && $workspace->polar_current_period_end !== null => $workspace->polar_current_period_end,
            default => $periodStart->copy()->addMonth(),
        };

        return [
            'plan' => $plan,
            'plan_name' => config("billing.plans.{$plan}.name", $plan),
            'credits_remaining' => $workspace->totalCredits(),
            'credits_monthly' => (int) $workspace->credits_balance,
            'credits_purchased' => (int) $workspace->purchased_credits,
            'credits_grant' => $grant,
            'credits_renew' => $renews,
            'credits_period_start' => $periodStart->toIso8601String(),
            'credits_period_ends_at' => $periodEnds?->toIso8601String(),
            'burn_table' => $this->burnTable(),
            'review_retention_days' => $workspace->reviewRetentionDays(),
            'pro_price_usd' => (int) config('billing.plans.pro.price_usd', 9),
            'pro_credits' => (int) config('billing.plans.pro.credits', 100),
            'packs' => $this->packs(),
        ];
    }

    /**
     * @return list<array{product: string, name: string, credits: int, price_usd: int}>
     */
    public function packs(): array
    {
        return collect(config('billing.packs', []))
            ->map(fn (array $pack, string $key) => [
                'product' => $key,
                'name' => (string) ($pack['name'] ?? $key),
                'credits' => (int) ($pack['credits'] ?? 0),
                'price_usd' => (int) ($pack['price_usd'] ?? 0),
            ])
            ->values()
            ->all();
    }
}
