<?php

namespace App\Console\Commands;

use App\Models\OAuthClient;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

/**
 * Assistants register themselves on every connect (Claude did twice in one
 * try), and most of those registrations never get a token. This removes the
 * ones that registered over a week ago and hold nothing: no token, no code.
 * Anything still connected keeps its tokens, so it is never touched.
 */
class PruneOAuthClients extends Command
{
    protected $signature = 'revisemy:prune-oauth-clients {--days=7 : Keep registrations newer than this}';

    protected $description = 'Remove assistant registrations that never connected';

    public function handle(): int
    {
        $pruned = OAuthClient::query()
            ->whereNull('owner_id')
            ->where('created_at', '<', now()->subDays(max(1, (int) $this->option('days'))))
            ->whereNotExists(fn (Builder $query) => $query->from('oauth_access_tokens')->whereColumn('oauth_access_tokens.client_id', 'oauth_clients.id'))
            ->whereNotExists(fn (Builder $query) => $query->from('oauth_auth_codes')->whereColumn('oauth_auth_codes.client_id', 'oauth_clients.id'))
            ->whereNotExists(fn (Builder $query) => $query->from('oauth_device_codes')->whereColumn('oauth_device_codes.client_id', 'oauth_clients.id'))
            ->delete();

        $this->line("Removed {$pruned} unused assistant ".($pruned === 1 ? 'registration' : 'registrations').'.');

        return self::SUCCESS;
    }
}
