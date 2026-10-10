<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * The reviews one try workspace can see: the same list your agent gets from
 * list_reviews. Opens by itself for a browser that connected over OAuth;
 * otherwise a try token, pasted or remembered from the connect hub.
 */
new class extends Component
{
    public string $tryToken = '';

    public ?string $error = null;

    /** @var list<array<string, mixed>> */
    public array $reviews = [];

    #[Locked]
    public ?int $workspaceId = null;

    public function mount(): void
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && $user->workspace) {
            $this->show($user->workspace);
        }
    }

    public function restoreToken(string $token): void
    {
        $this->tryToken = trim($token);
        $this->loadReviews();
    }

    public function loadReviews(): void
    {
        $this->error = null;
        $access = PersonalAccessToken::findToken(trim($this->tryToken));
        $user = $access?->tokenable;

        if (! $access || ($access->expires_at && $access->expires_at->isPast()) || ! $user instanceof User || ! $user->workspace) {
            $this->error = 'That try token isn’t valid any more. Connect again to get a new one.';

            return;
        }

        $this->show($user->workspace);
    }

    protected function show(Workspace $workspace): void
    {
        $this->workspaceId = $workspace->id;
        $this->reviews = $workspace->reviews()
            ->latest()
            ->limit(20)
            ->with(['screenshots.annotations', 'parent'])
            ->get()
            ->map(fn ($review) => $review->toListSummary())
            ->values()
            ->all();
    }

    /** Signs out a browser that Connect remembered, so it stops opening this list. */
    public function forgetBrowser(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/reviews');
    }

    public function clearToken(): void
    {
        $this->tryToken = '';
        $this->reviews = [];
        $this->workspaceId = null;
        $this->error = null;
        $this->dispatch('revisemy-try-token', token: null);
    }
};
?>

<div
    class="relative"
    x-data="{
        init() {
            let saved = null;
            try { saved = sessionStorage.getItem('revisemy_try_token') } catch (e) {}
            if (saved && ! {{ $workspaceId ? 'true' : 'false' }}) { $wire.restoreToken(saved) }
        },
        // Alpine runs an event handler as an expression, so the try lives here.
        forgetSaved(token) {
            if (token) return;
            try { sessionStorage.removeItem('revisemy_try_token') } catch (e) {}
        },
    }"
    x-on:revisemy-try-token.window="forgetSaved($event.detail.token)"
>
    <x-site-shell :cta="false">
        <x-home-section first>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">Your reviews</h1>
                <a href="/connect" class="link text-sm">Connect an assistant</a>
            </div>
            <div class="mt-8 max-w-3xl">
                @if (session('status'))
                    <p class="mb-6 rounded-xl bg-done-soft px-4 py-3 text-sm text-done-ink" role="status">{{ session('status') }}</p>
                @endif
                @if (! $workspaceId)
                    <p class="text-sm text-muted-foreground">Reviews your try token can see — the same list your agent gets.</p>
                    <form wire:submit="loadReviews" class="mt-5 flex flex-col gap-2 rounded-2xl bg-card p-4 sm:flex-row sm:p-5">
                        <label class="sr-only" for="try-token">Try token</label>
                        <input
                            id="try-token"
                            type="password"
                            wire:model="tryToken"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Paste your try token"
                            class="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2 font-mono text-sm text-zinc-800 outline-none placeholder:text-zinc-400 focus:border-zinc-400"
                        />
                        <flux:button type="submit" variant="primary" class="shrink-0">Show reviews</flux:button>
                    </form>
                    @if ($error)
                        <p class="mt-3 text-sm text-problem-ink" role="alert">{{ $error }}</p>
                    @endif
                @else
                    @if ($reviews === [])
                        <div class="hatch rounded-2xl px-6 py-10 text-center text-zinc-300">
                            <p class="text-base font-semibold text-zinc-900">No reviews yet</p>
                            <p class="mt-1 text-sm text-muted-foreground">Ask your agent for a design checkup, and it lands here.</p>
                        </div>
                    @else
                        <ul class="space-y-2.5">
                            @foreach ($reviews as $item)
                                @php($tone = ['changes_requested' => 'attention', 'approved' => 'done'][$item['status']] ?? 'neutral')
                                <li class="flex items-center gap-4 rounded-2xl bg-card p-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-signal-tag :tone="$tone">{{ $item['status_label'] }}</x-signal-tag>
                                            <span class="text-xs text-muted-foreground">Pass {{ $item['pass'] }}</span>
                                        </div>
                                        <h2 class="mt-1.5 truncate text-base font-semibold text-zinc-900">{{ $item['title'] }}</h2>
                                        <p class="mt-0.5 text-xs tabular-nums text-muted-foreground">
                                            {{ $item['loop']['outstanding_count'] }} open
                                            @if ($item['loop']['awaiting_verification_count'] > 0)
                                                · {{ $item['loop']['awaiting_verification_count'] }} to verify
                                            @endif
                                        </p>
                                    </div>
                                    <a href="{{ $item['review_url'] }}" class="btn-quiet inline-flex shrink-0 items-center rounded-full px-3 py-1.5 text-xs font-medium">Open</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-8">
                        <livewire:connected-assistants :workspace-id="$workspaceId" :key="'assistants-'.$workspaceId" />
                    </div>

                    <div class="mt-8">
                        <livewire:review-phrases :workspace-id="$workspaceId" :key="'phrases-'.$workspaceId" />
                    </div>

                    <div class="mt-8">
                        <livewire:design-rules :workspace-id="$workspaceId" :key="'rules-'.$workspaceId" />
                    </div>

                    @if ($tryToken !== '')
                        <button type="button" wire:click="clearToken" class="mt-6 text-xs text-muted-foreground underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900">Forget this token in this browser</button>
                    @else
                        <button type="button" wire:click="forgetBrowser" class="mt-6 text-xs text-muted-foreground underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900">Forget this browser</button>
                    @endif
                @endif
            </div>
        </x-home-section>
    </x-site-shell>
</div>
