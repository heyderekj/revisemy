<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\BillingService;
use App\Services\CreditsService;
use Illuminate\Console\Command;

class ExtendTry extends Command
{
    protected $signature = 'revisemy:extend-try
                            {workspace : Workspace public_id (e.g. from /billing/checkout/{id})}
                            {--credits= : Credits to add to the balance}
                            {--token-days= : Days to extend each API token from max(now, current expiry)}
                            {--pack : Grant a full Try pack + extend tokens by try token_days}';

    protected $description = 'Support: top up Try credits and/or extend API token expiry for a workspace';

    public function handle(CreditsService $credits, BillingService $billing): int
    {
        $publicId = (string) $this->argument('workspace');
        $workspace = Workspace::query()->where('public_id', $publicId)->first();

        if (! $workspace) {
            $this->error("No workspace with public_id [{$publicId}].");

            return self::FAILURE;
        }

        $addCredits = $this->option('pack')
            ? (int) config('billing.plans.free.credits', 30)
            : (int) ($this->option('credits') ?: 0);

        $tokenDays = $this->option('pack')
            ? (int) config('billing.plans.free.token_days', 14)
            : (int) ($this->option('token-days') ?: 0);

        if ($addCredits <= 0 && $tokenDays <= 0) {
            $this->error('Pass --credits=N, --token-days=N, and/or --pack.');

            return self::FAILURE;
        }

        $beforeBalance = (int) $workspace->credits_balance;
        $tokenBefore = $this->tokenExpirySummary($workspace);

        if ($addCredits > 0) {
            $workspace = $credits->addCredits($workspace, $addCredits);
        }

        if ($tokenDays > 0) {
            $billing->extendApiTokensByDays($workspace, $tokenDays);
        }

        $workspace->refresh();

        $this->info("Workspace {$workspace->public_id} ({$workspace->plan} / ".config('billing.plans.'.$workspace->normalizedPlan().'.name', $workspace->plan).')');
        $this->line("Credits: {$beforeBalance} → ".(int) $workspace->credits_balance.($addCredits > 0 ? " (+{$addCredits})" : ''));
        $this->line('Tokens before: '.$tokenBefore);
        $this->line('Tokens after:  '.$this->tokenExpirySummary($workspace));

        return self::SUCCESS;
    }

    protected function tokenExpirySummary(Workspace $workspace): string
    {
        $expires = [];

        foreach ($workspace->users as $user) {
            foreach ($user->tokens as $token) {
                $expires[] = $token->expires_at
                    ? $token->expires_at->toIso8601String()
                    : 'never';
            }
        }

        return $expires === [] ? '(no tokens)' : implode(', ', $expires);
    }
}
