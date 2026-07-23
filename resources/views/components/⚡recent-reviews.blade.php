<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

new class extends Component
{
    public string $tryToken = '';

    public ?string $error = null;

    /** @var list<array<string, mixed>> */
    public array $reviews = [];

    public bool $loaded = false;

    public function mount(): void
    {
        // Token is restored client-side from sessionStorage (same as homepage setup).
    }

    public function restoreToken(string $token): void
    {
        $this->tryToken = trim($token);
        $this->loadReviews();
    }

    public function loadReviews(): void
    {
        $this->error = null;
        $this->reviews = [];
        $this->loaded = false;

        $token = trim($this->tryToken);

        if ($token === '') {
            $this->error = 'Paste your try-token Bearer value from the homepage setup.';

            return;
        }

        $access = PersonalAccessToken::findToken($token);

        if (! $access || ($access->expires_at && $access->expires_at->isPast())) {
            $this->error = 'That try token is missing or expired. Generate a new one on the homepage.';

            return;
        }

        $user = $access->tokenable;

        if (! $user instanceof User || ! $user->workspace) {
            $this->error = 'That try token is not linked to a workspace.';

            return;
        }

        $this->reviews = $user->workspace->reviews()
            ->latest()
            ->limit(20)
            ->with(['screenshots.annotations', 'parent'])
            ->get()
            ->map(fn ($review) => $review->toListSummary())
            ->values()
            ->all();

        $this->loaded = true;
    }

    public function clearToken(): void
    {
        $this->tryToken = '';
        $this->reviews = [];
        $this->loaded = false;
        $this->error = null;
        $this->dispatch('revisemy-recent-token-cleared');
    }
};
?>

<div
    class="min-h-svh bg-zinc-50"
    x-data
    x-init="
        const raw = sessionStorage.getItem('revisemy_try_setup');
        if (raw && ! @js($tryToken !== '')) {
            try {
                const d = JSON.parse(raw);
                if (d.token) $wire.restoreToken(d.token);
            } catch (e) {}
        }
    "
    x-on:revisemy-recent-token-cleared.window="sessionStorage.removeItem('revisemy_try_setup')"
>
    <header class="border-b border-zinc-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo size="sm" />
                </a>
                <h1 class="text-lg font-semibold tracking-tight text-zinc-900">Recent reviews</h1>
            </div>
            <a href="/#setup" class="text-sm font-medium text-rose-600 hover:text-rose-500">Get a try token</a>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <p class="max-w-2xl text-sm leading-relaxed text-zinc-600">
            Token-scoped memory for this try workspace — no account. Same list your agent sees via <code class="font-mono text-[13px]">list_reviews</code>.
        </p>

        <form wire:submit="loadReviews" class="mt-6 space-y-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-5">
            <label class="block text-xs font-medium uppercase tracking-wider text-zinc-400" for="try-token">Bearer try token</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input
                    id="try-token"
                    type="password"
                    wire:model="tryToken"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="Paste your try token"
                    class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 font-mono text-sm text-zinc-800 outline-none ring-0 placeholder:text-zinc-400 focus:border-zinc-400 focus:bg-white"
                />
                <flux:button type="submit" variant="primary" class="shrink-0">Load reviews</flux:button>
            </div>
            @if ($loaded)
                <button type="button" wire:click="clearToken" class="text-xs font-medium text-zinc-500 hover:text-zinc-800">Clear token from this browser</button>
            @endif
        </form>

        @if ($error)
            <flux:callout variant="danger" class="mt-4">
                <flux:callout.text>{{ $error }}</flux:callout.text>
            </flux:callout>
        @endif

        @if ($loaded)
            @if ($reviews === [])
                <p class="mt-8 text-sm text-zinc-500">No reviews yet for this token. Ask your agent to <code class="font-mono text-[13px]">create_review</code>.</p>
            @else
                <ul class="mt-8 space-y-3">
                    @foreach ($reviews as $item)
                        <li class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600">Pass {{ $item['pass'] }}</span>
                                        <span @class([
                                            'rounded-md border px-1.5 py-0.5 text-[10px] font-medium',
                                            'border-amber-200 bg-amber-50 text-amber-800' => $item['status'] === 'changes_requested',
                                            'border-emerald-200 bg-emerald-50 text-emerald-800' => $item['status'] === 'approved',
                                            'border-rose-200 bg-rose-50 text-rose-800' => $item['status'] === 'expired',
                                            'border-zinc-200 bg-zinc-50 text-zinc-600' => $item['status'] === 'pending',
                                        ])>{{ $item['status'] }}</span>
                                        <span class="text-[10px] uppercase tracking-wide text-zinc-400">{{ $item['type'] }}</span>
                                    </div>
                                    <h2 class="mt-1.5 truncate text-base font-semibold text-zinc-900">{{ $item['title'] }}</h2>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $item['status_label'] }}</p>
                                </div>
                                <a
                                    href="{{ $item['review_url'] }}"
                                    class="inline-flex shrink-0 items-center rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-zinc-800"
                                >Open review</a>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-2 text-[11px] tabular-nums text-zinc-500 sm:grid-cols-4">
                                <div>
                                    <dt class="text-zinc-400">Outstanding</dt>
                                    <dd class="font-medium text-zinc-700">{{ $item['loop']['outstanding_count'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-zinc-400">Must fix</dt>
                                    <dd class="font-medium text-zinc-700">{{ $item['loop']['must_fix_count'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-zinc-400">Awaiting verify</dt>
                                    <dd class="font-medium text-zinc-700">{{ $item['loop']['awaiting_verification_count'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-zinc-400">Next action</dt>
                                    <dd class="font-medium text-zinc-700">{{ $item['next_action'] }}</dd>
                                </div>
                            </dl>
                            @if (! empty($item['board_url']) && $item['loop']['awaiting_verification_count'] > 0)
                                <a href="{{ $item['board_url'] }}" class="mt-2 inline-block text-xs font-medium text-rose-600 hover:text-rose-500">Open board</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </main>
</div>
