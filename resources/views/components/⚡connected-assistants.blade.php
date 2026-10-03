<?php

use App\Models\OAuthClient;
use App\Models\Workspace;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * What can reach this try workspace, and the way to take it back: each app
 * that signed in (OAuth), and each try token. Disconnecting revokes access;
 * reviews stay. After Koati's Settings → Keys.
 *
 * The workspace id is locked: only a page that already holds the token or
 * the signed-in session can render this for it.
 */
new class extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Computed]
    public function workspace(): ?Workspace
    {
        return Workspace::query()->find($this->workspaceId);
    }

    /**
     * @return list<array{kind: string, id: string, name: string, seen: ?string}>
     */
    #[Computed]
    public function connections(): array
    {
        $userIds = $this->workspace?->users()->pluck('id') ?? collect();

        $apps = Token::query()
            ->whereIn('user_id', $userIds)
            ->where('revoked', false)
            ->where('expires_at', '>', now()->subDays(60))
            ->get()
            ->groupBy('client_id')
            ->map(fn ($tokens, $clientId) => [
                'kind' => 'app',
                'id' => (string) $clientId,
                'name' => (string) (OAuthClient::query()->whereKey($clientId)->value('name') ?? 'An app'),
                'seen' => $tokens->max('created_at')?->diffForHumans(),
            ]);

        $keys = PersonalAccessToken::query()
            ->where('tokenable_type', \App\Models\User::class)
            ->whereIn('tokenable_id', $userIds)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'kind' => 'token',
                'id' => (string) $token->id,
                'name' => 'Try token from '.$token->created_at?->toFormattedDateString(),
                'seen' => $token->last_used_at ? 'used '.$token->last_used_at->diffForHumans() : 'not used yet',
            ]);

        return $apps->values()->concat($keys)->all();
    }

    public function disconnect(string $kind, string $id): void
    {
        $userIds = $this->workspace?->users()->pluck('id') ?? collect();

        if ($kind === 'app') {
            $tokens = Token::query()->whereIn('user_id', $userIds)->where('client_id', $id);
            RefreshToken::query()->whereIn('access_token_id', (clone $tokens)->pluck('id'))->update(['revoked' => true]);
            $tokens->update(['revoked' => true]);
        }

        if ($kind === 'token') {
            PersonalAccessToken::query()
                ->where('tokenable_type', \App\Models\User::class)
                ->whereIn('tokenable_id', $userIds)
                ->whereKey($id)
                ->delete();
        }

        unset($this->connections);
    }
};
?>

<div>
    @if ($this->connections !== [])
        <p class="mb-2 text-sm font-medium text-zinc-700">Connected to this workspace</p>
        <ul class="divide-y divide-black/[0.05] overflow-hidden rounded-2xl bg-card">
            @foreach ($this->connections as $connection)
                <li class="flex items-center gap-3 px-4 py-3" wire:key="{{ $connection['kind'] }}-{{ $connection['id'] }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900">{{ $connection['name'] }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $connection['kind'] === 'app' ? 'Signed in '.$connection['seen'] : 'Pasted where it’s used · '.$connection['seen'] }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 shrink-0 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover"
                        wire:click="disconnect('{{ $connection['kind'] }}', '{{ $connection['id'] }}')"
                    >{{ $connection['kind'] === 'app' ? 'Disconnect' : 'Revoke' }}</button>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-muted-foreground">Disconnecting takes away access. Your reviews stay.</p>
    @endif
</div>
