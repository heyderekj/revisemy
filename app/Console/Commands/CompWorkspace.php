<?php

namespace App\Console\Commands;

use App\Models\Review;
use App\Models\Workspace;
use App\Services\BillingService;
use App\Services\CreditsService;
use Illuminate\Console\Command;

class CompWorkspace extends Command
{
    protected $signature = 'revisemy:comp
                            {workspace : Workspace public_id, or any owner review link (…/r/{token}) from that workspace}
                            {--off : Take the comp away; the workspace drops to Try unless it pays for Plus}
                            {--note= : Who it is for, kept on the workspace}';

    protected $description = 'Support: give a workspace Plus without a subscription, or take it back';

    public function handle(CreditsService $credits, BillingService $billing): int
    {
        $target = trim((string) $this->argument('workspace'));
        $workspace = $this->resolve($target);

        if (! $workspace) {
            $this->error("No workspace for [{$target}]. Pass its public_id or an owner review link.");

            return self::FAILURE;
        }

        if ($this->option('off')) {
            $workspace = $credits->comp($workspace, false);
            $this->info("Workspace {$workspace->public_id} is no longer comped ({$workspace->plan}).");

            return self::SUCCESS;
        }

        $workspace = $credits->comp($workspace, true, $this->option('note') ?: null);
        $billing->extendApiTokensByDays($workspace, (int) config('billing.plans.pro.token_days', 365));

        $this->info("Workspace {$workspace->public_id} is comped to Plus.");
        $this->line('Credits: '.(int) $workspace->credits_balance.' this month, refilled monthly from '.$workspace->credits_period_start?->toDateString().'.');

        return self::SUCCESS;
    }

    protected function resolve(string $target): ?Workspace
    {
        if ($workspace = Workspace::query()->where('public_id', $target)->first()) {
            return $workspace;
        }

        // An owner review link, or its bare token. Guest links never count.
        $token = preg_match('~/r/([A-Za-z0-9]+)~', $target, $m) ? $m[1] : $target;
        $review = Review::query()->where('token', $token)->first();

        return $review?->workspace;
    }
}
