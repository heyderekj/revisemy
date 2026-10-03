<?php

use App\Models\User;
use App\Models\Workspace;
use App\Services\TryTokenGate;
use App\Services\TryTokenService;
use App\Support\Hosts;
use App\Support\InstallLinks;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * The one list of ways to connect an assistant, on /connect, the homepage's
 * Setup and /connectors. Pick your assistant, follow two or three steps, and
 * the page proves it worked: "Waiting for Claude's first call…" turns into
 * "Claude is connected" the moment a tool is called (RecordAssistantCall).
 *
 * The bar is Koati's (docs/ui.md § Connecting, after Meta Muse's
 * connectors): one list, the provider's own sign-in, proven on the spot,
 * disconnect from the same place. Hosts live in config/hosts.php `connect`.
 */
new class extends Component
{
    /** A try token minted here, for the hosts that can't sign in yet. */
    #[Locked]
    public ?string $token = null;

    #[Locked]
    public ?string $tokenExpiresAt = null;

    /** Once listening starts, a call after this moment proves the connection. */
    #[Locked]
    public ?int $since = null;

    public ?string $error = null;

    public function mintToken(TryTokenService $tryTokens, TryTokenGate $gate): void
    {
        $this->error = null;

        try {
            $gate->assertCanMint(request());
            $result = $tryTokens->create();
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage() === TryTokenGate::MESSAGE
                ? $e->getMessage()
                : 'Couldn’t start a try just now. Give it a minute and try again.';

            if ($e->getMessage() !== TryTokenGate::MESSAGE) {
                report($e);
            }

            return;
        }

        $this->token = $result['token'];
        $this->tokenExpiresAt = $result['token_expires_at'];
        $this->listen();

        $this->dispatch('revisemy-try-token', token: $this->token);
    }

    /** Put back a try token this browser minted earlier (sessionStorage). */
    public function restoreToken(string $token): void
    {
        $found = PersonalAccessToken::findToken($token);

        if (! $found || ($found->expires_at && $found->expires_at->isPast())) {
            $this->dispatch('revisemy-try-token', token: null);

            return;
        }

        $this->token = $token;
        $this->tokenExpiresAt = $found->expires_at?->toIso8601String();
    }

    public function forgetToken(): void
    {
        $this->token = null;
        $this->tokenExpiresAt = null;
        $this->dispatch('revisemy-try-token', token: null);
    }

    /** The visitor copied an address or clicked an install link. */
    public function listen(): void
    {
        $this->since ??= now()->timestamp;
    }

    #[Computed]
    public function workspace(): ?Workspace
    {
        if ($this->token) {
            $user = PersonalAccessToken::findToken($this->token)?->tokenable;

            if ($user instanceof User && $user->workspace) {
                return $user->workspace;
            }
        }

        // Connected over OAuth in this browser: /connect signed it in.
        $user = Auth::guard('web')->user();

        return $user instanceof User ? $user->workspace : null;
    }

    /**
     * @return array{state: string, name?: string, tool?: string, at?: string}
     */
    #[Computed]
    public function status(): array
    {
        $workspace = $this->workspace;

        if (! $workspace) {
            return ['state' => $this->since ? 'waiting' : 'idle'];
        }

        $workspace->refresh();
        $seen = $workspace->assistant_seen_at;

        if ($seen && (! $this->since || $seen->timestamp >= $this->since - 5)) {
            return [
                'state' => 'connected',
                'name' => (string) $workspace->assistant_name,
                'tool' => (string) $workspace->assistant_last_tool,
                'at' => $seen->diffForHumans(),
            ];
        }

        return ['state' => $this->since ? 'waiting' : 'idle'];
    }

    public function with(): array
    {
        return [
            'hosts' => Hosts::all($this->token),
            'url' => Hosts::mcpUrl(),
            'links' => InstallLinks::for(),
            'firstPrompt' => Hosts::firstPrompt(),
        ];
    }
};
?>

@php($status = $this->status)
<div
    x-data="{
        host: 'claude',
        init() {
            const fromHash = window.location.hash.replace(/^#/, '');
            if (@js(array_keys($hosts)).includes(fromHash)) { this.host = fromHash }
            else { try { this.host = localStorage.getItem('revisemy_connect_host') || 'claude' } catch (e) {} }
            let saved = null;
            try { saved = sessionStorage.getItem('revisemy_try_token') } catch (e) {}
            if (saved && ! {{ $token ? 'true' : 'false' }}) { $wire.restoreToken(saved) }
        },
        pick(id) {
            this.host = id;
            try { localStorage.setItem('revisemy_connect_host', id) } catch (e) {}
        },
        remember(token) {
            try { token ? sessionStorage.setItem('revisemy_try_token', token) : sessionStorage.removeItem('revisemy_try_token') } catch (e) {}
        },
    }"
    x-on:revisemy-try-token.window="remember($event.detail.token)"
    x-on:rm-connect-started="$wire.listen()"
    @if ($status['state'] === 'waiting') wire:poll.3s @endif
    class="space-y-5"
>
    {{-- One list. --}}
    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4" role="tablist" aria-label="Your assistant">
        @foreach ($hosts as $id => $host)
            <button
                type="button"
                role="tab"
                class="flex min-w-0 items-center gap-2.5 rounded-xl px-3 py-2.5 text-left transition-colors"
                x-bind:class="host === '{{ $id }}' ? 'bg-raised shadow-sm ring-1 ring-black/[0.07]' : 'bg-card hover:bg-chip'"
                x-bind:aria-selected="(host === '{{ $id }}').toString()"
                x-on:click="pick('{{ $id }}')"
            >
                <x-host-icon :name="$host['icon']" size="lg" class="text-zinc-800" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-zinc-900">{{ $host['name'] }}</span>
                    <span class="block truncate text-xs text-muted-foreground">
                        {{ \App\Support\Hosts::modeLabel($host['mode']) }}
                    </span>
                </span>
            </button>
        @endforeach
    </div>

    {{-- The chosen one. --}}
    @foreach ($hosts as $id => $host)
        <div x-show="host === '{{ $id }}'" @if ($id !== 'claude') x-cloak @endif class="rounded-2xl bg-card p-5 sm:p-6" role="tabpanel">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900">Connect {{ $host['name'] }}</h3>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $host['where'] }}@if ($host['inline']) · reviews open right in the chat @endif</p>
                </div>
                <x-host-icon :name="$host['icon']" size="lg" class="mt-1 text-zinc-400" />
            </div>

            <div class="mt-5 space-y-4">
                @if ($host['needs_token'] && ! $token)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-raised p-4 ring-1 ring-black/[0.05]">
                        <p class="min-w-0 flex-1 text-sm text-zinc-600">{{ $host['name'] }} connects with a try token. It’s free, needs no account, and makes a workspace that’s yours.</p>
                        <button type="button" wire:click="mintToken" wire:loading.attr="disabled" class="btn-lit inline-flex h-9 shrink-0 items-center rounded-full px-4 text-sm font-medium">
                            <span wire:loading.remove wire:target="mintToken">Get a try token</span>
                            <span wire:loading wire:target="mintToken">Getting one…</span>
                        </button>
                    </div>
                @endif

                <ol class="space-y-2.5">
                    @foreach ($host['steps'] as $step)
                        <li class="flex gap-3 text-[15px] leading-relaxed text-zinc-700">
                            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-chip text-xs font-semibold tabular-nums text-zinc-600">{{ $loop->iteration }}</span>
                            <span class="min-w-0">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($host['mode'] === 'deeplink')
                    <a
                        href="{{ $links[$id] }}"
                        class="btn-lit inline-flex h-10 items-center gap-2 rounded-full px-5 text-sm font-medium"
                        x-on:click="$dispatch('rm-connect-started'); if (window.fathom) fathom.trackEvent('Install link {{ $host['name'] }}')"
                    >
                        <x-host-icon :name="$host['icon']" size="md" /> Add to {{ $host['name'] }}
                    </a>
                @elseif ($host['mode'] === 'oauth' && ! $host['command'])
                    <x-copy-field :value="$url" label="The address" />
                @endif

                @if ($host['command'] && (! $host['needs_token'] || $token))
                    @if ($host['needs_token'] && $id !== 'muse')
                        <x-copy-field value="export REVISEMY_TOKEN={{ $token }}" label="Your try token" />
                    @endif
                    <x-copy-field :value="$host['command']" :label="$id === 'muse' ? 'Paste to Muse' : 'Then run'" :mono="$id !== 'muse'" />
                @endif

                @if ($host['note'])
                    <p class="text-sm text-muted-foreground">{{ $host['note'] }}</p>
                @endif

                @if ($host['needs_token'] && $token)
                    <p class="text-xs text-muted-foreground">
                        @if ($tokenExpiresAt)
                            This try token lasts until {{ \Illuminate\Support\Carbon::parse($tokenExpiresAt)->toFormattedDateString() }}.
                        @endif
                        <button type="button" wire:click="forgetToken" class="underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900">Use a different one</button>
                    </p>
                @endif
            </div>
        </div>
    @endforeach

    @if ($error)
        <p class="text-sm text-problem-ink" role="alert">{{ $error }}</p>
    @endif

    {{-- Proven on the spot. --}}
    @if ($status['state'] !== 'idle')
        <div @class([
            'flex flex-wrap items-center gap-3 rounded-2xl p-4',
            'bg-done-soft text-done-ink' => $status['state'] === 'connected',
            'bg-well text-zinc-700' => $status['state'] === 'waiting',
        ]) role="status" aria-live="polite">
            @if ($status['state'] === 'connected')
                <flux:icon.check-circle variant="mini" class="size-5 shrink-0" />
                <p class="min-w-0 flex-1 text-sm">
                    <span class="font-medium">{{ $status['name'] }} is connected</span>
                    — it ran {{ $status['tool'] }} {{ $status['at'] }}.
                </p>
            @else
                <span class="relative flex size-2.5 shrink-0" aria-hidden="true">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-attention opacity-60"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-attention"></span>
                </span>
                <p class="min-w-0 flex-1 text-sm">Waiting for your assistant’s first call…</p>
            @endif
        </div>
        <x-copy-field :value="$firstPrompt" label="Then ask it" :mono="false" />
        @if ($this->workspace)
            <livewire:connected-assistants :workspace-id="$this->workspace->id" :key="'assistants-'.$this->workspace->id" />
        @endif
    @endif

    <p class="text-sm text-muted-foreground">
        Another MCP client? Give it <span class="font-mono text-[13px] text-zinc-700">{{ $url }}</span>. It signs in the same way, or takes a try token as a Bearer header.
    </p>
</div>
