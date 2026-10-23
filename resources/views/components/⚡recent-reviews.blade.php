<?php

use App\Models\Annotation;
use App\Models\Review;
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
            ->map(fn (Review $review) => $review->toListSummary() + $this->rowExtras($review))
            ->values()
            ->all();
    }

    /**
     * What the row shows beyond the agent's list summary, read from the
     * relations already loaded so twenty rows stay one query per relation.
     *
     * @return array<string, mixed>
     */
    protected function rowExtras(Review $review): array
    {
        $shot = $review->screenshots->first();
        // Hand the shot its review so thumbUrl() doesn't fetch it again.
        $shot?->setRelation('review', $review);

        $marks = $review->screenshots->flatMap->annotations;
        $updated = collect([$review->updated_at])->merge($marks->pluck('updated_at'))->filter()->max();

        return [
            'tag' => $review->statusTag(),
            'type_label' => $review->typeLabel(),
            'domain' => $review->sourceDomain(),
            'thumb_url' => $shot?->thumbUrl(),
            'share_url' => $review->shareUrl(),
            'mark_count' => $marks->count(),
            'guest_mark_count' => $marks->where('source', Annotation::SOURCE_GUEST)->count(),
            'updated_at' => $updated?->toIso8601String(),
        ];
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
        copied: null,
        async copy(text, said) {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = said;
            } catch (e) {
                this.copied = 'Couldn’t copy the link';
            }
            clearTimeout(this.copiedTimer);
            this.copiedTimer = setTimeout(() => this.copied = null, 1800);
        },
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
                        {{-- One line per review: the whole row opens it, the menu copies links. --}}
                        <ul class="divide-y divide-border rounded-2xl bg-card">
                            @foreach ($reviews as $item)
                                @php
                                    $marks = $item['loop'];
                                    $counts = array_filter([
                                        $marks['outstanding_count'] > 0 ? $marks['outstanding_count'].' open' : null,
                                        $marks['awaiting_verification_count'] > 0 ? $marks['awaiting_verification_count'].' to verify' : null,
                                        $marks['outstanding_count'] + $marks['awaiting_verification_count'] === 0 && $marks['verified_count'] > 0 ? $marks['verified_count'].' verified' : null,
                                        $item['mark_count'] === 0 ? 'No marks yet' : null,
                                        $item['guest_mark_count'] > 0 ? $item['guest_mark_count'].' '.str('guest mark')->plural($item['guest_mark_count']) : null,
                                    ]);
                                    $updated = $item['updated_at'] ? \Illuminate\Support\Carbon::parse($item['updated_at']) : null;
                                @endphp
                                <li wire:key="review-{{ $item['id'] }}" class="relative flex items-center gap-3 px-3 py-2.5 transition-colors first:rounded-t-2xl last:rounded-b-2xl hover:bg-chip/60 has-[a:focus-visible]:bg-chip/60 sm:gap-4 sm:px-4">
                                    <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-md border border-border bg-chip sm:w-14" aria-hidden="true">
                                        @if ($item['thumb_url'])
                                            <img src="{{ $item['thumb_url'] }}" alt="" loading="lazy" class="size-full object-cover object-top" onerror="this.remove()" />
                                        @endif
                                    </div>

                                    {{-- Status sits under the title on a phone, to the right from sm up. --}}
                                    <div class="min-w-0 flex-1 sm:flex sm:items-center sm:gap-4">
                                        <div class="min-w-0 sm:flex-1">
                                            <a href="{{ $item['review_url'] }}" class="block truncate text-sm font-semibold text-zinc-900 outline-none after:absolute after:inset-0 after:content-['']">{{ $item['title'] }}</a>
                                            <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                                {{ $item['type_label'] }}@if ($item['domain'])<span class="max-sm:hidden"> · {{ $item['domain'] }}</span>@endif
                                                · Pass {{ $item['pass'] }}
                                                @if ($updated)
                                                    · <time datetime="{{ $item['updated_at'] }}" title="{{ $updated->timezone(config('app.timezone'))->toDayDateTimeString() }}">{{ $updated->diffForHumans(short: true) }}</time>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 sm:mt-0 sm:shrink-0 sm:flex-col sm:items-end sm:gap-1">
                                            <x-signal-tag :tone="$item['tag']['tone']">{{ $item['tag']['label'] }}</x-signal-tag>
                                            <span class="text-xs tabular-nums text-muted-foreground">
                                                @if ($marks['must_fix_count'] > 0)
                                                    <span class="inline-flex items-center gap-1"><span class="size-1.5 rounded-full {{ \App\Models\Annotation::TONES['problem']['dot'] }}" aria-hidden="true"></span>{{ $marks['must_fix_count'] }} must fix</span> ·
                                                @endif
                                                {{ implode(' · ', $counts) }}
                                            </span>
                                        </div>
                                    </div>

                                    <flux:dropdown position="bottom" align="end" class="relative z-10 shrink-0">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" class="!size-8 !px-0" aria-label="More for {{ $item['title'] }}" />
                                        <flux:menu class="min-w-48">
                                            <flux:menu.item icon="clipboard-document" x-on:click="copy({{ \Illuminate\Support\Js::from($item['review_url']) }}, 'Review link copied')">Copy review link</flux:menu.item>
                                            <flux:menu.item icon="link" x-on:click="copy({{ \Illuminate\Support\Js::from($item['share_url']) }}, 'Guest link copied')">Copy guest link</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="view-columns" href="{{ $item['board_url'] }}">Open board</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
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

                    @if ($tryToken !== '')
                        <button type="button" wire:click="clearToken" class="mt-6 text-xs text-muted-foreground underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900">Forget this token in this browser</button>
                    @else
                        <button type="button" wire:click="forgetBrowser" class="mt-6 text-xs text-muted-foreground underline decoration-zinc-300 underline-offset-2 hover:text-zinc-900">Forget this browser</button>
                    @endif
                @endif
            </div>
        </x-home-section>
    </x-site-shell>
    <div
        x-show="copied"
        x-cloak
        x-transition.opacity
        role="status"
        class="fixed inset-x-0 bottom-6 z-50 mx-auto w-fit rounded-full bg-foreground px-4 py-2 text-sm font-medium text-background shadow-lg"
        x-text="copied"
    ></div>
</div>
