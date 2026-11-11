<x-layouts.app title="Feedback requested — ReviseMy" description="Someone asked for your feedback."
    robots="noindex, nofollow" schema="none" :analytics="false">
    <div class="min-h-dvh bg-well">
        <header class="border-b border-border bg-background">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="/" aria-label="ReviseMy home"><x-revisemy-logo size="sm" /></a>
                <span class="text-xs font-medium text-muted-foreground">Feedback request</span>
            </div>
        </header>

        <main class="mx-auto grid max-w-6xl items-start gap-5 px-4 py-5 sm:px-6 sm:py-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-6">
            <div class="min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900">{{ $feedback->review->title }}</h1>
                    @if ($mark)
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $mark->markerClass() }}">M{{ $mark->number }}</span>
                    @endif
                </div>

                @if ($shot)
                    @php($image = route('feedback.screenshot', [$feedback->token, $shot]))
                    @if ($mark?->screenshot_id === $shot->id && ! request()->boolean('full'))
                        <x-mark-focus-preview :mark="$mark" :image-url="$image" />
                        <a href="{{ $feedback->url() }}?full=1" class="link text-xs">View full screenshot</a>
                    @else
                        <div class="max-h-[62svh] overflow-auto rounded-xl border border-border bg-card">
                            <img src="{{ $image }}" width="{{ $shot->width }}" height="{{ $shot->height }}"
                                alt="Review screenshot" class="mx-auto block h-auto max-w-full">
                        </div>
                        @if ($mark?->screenshot_id === $shot->id)
                            <a href="{{ $feedback->url() }}" class="link text-xs">Focus on M{{ $mark->number }}</a>
                        @endif
                    @endif
                    @if ($shots->count() > 1)
                        <nav aria-label="Review screenshots" class="flex flex-wrap gap-1.5">
                            @foreach ($shots as $index => $candidate)
                                <a href="{{ $feedback->url() }}?shot={{ $candidate->id }}"
                                    @if ($candidate->is($shot)) aria-current="page" @endif
                                    @class(['rounded-full px-3 py-1.5 text-xs font-medium', 'bg-foreground text-background' => $candidate->is($shot), 'bg-chip text-muted-foreground hover:bg-chip-hover' => ! $candidate->is($shot)])>
                                    {{ $candidate->railLabel($index) }}
                                </a>
                            @endforeach
                        </nav>
                    @endif
                @endif
            </div>

            <section class="space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5" aria-labelledby="feedback-question">
                <div>
                    <h2 id="feedback-question" class="text-lg font-semibold tracking-tight text-zinc-900">What do you think?</h2>
                    <p class="mt-1 whitespace-pre-wrap break-words text-sm leading-relaxed text-zinc-800">{{ $feedback->question }}</p>
                </div>
                @if ($mark)
                    <div class="rounded-xl bg-well p-3 text-sm">
                        <p class="text-xs font-medium text-muted-foreground">Mark M{{ $mark->number }}</p>
                        <p class="mt-1 whitespace-pre-wrap break-words text-zinc-800">{{ $mark->body }}</p>
                    </div>
                @endif

                @if ($feedback->responded_at)
                    <div class="space-y-1 border-t border-border pt-4">
                        <p class="flex items-center gap-1.5 text-sm font-medium text-done-ink">
                            <flux:icon.check variant="micro" /> Feedback sent
                        </p>
                        <p class="whitespace-pre-wrap break-words text-sm text-zinc-800">{{ $feedback->response }}</p>
                    </div>
                @elseif ($feedback->review->allowsComments())
                    <form method="POST" action="{{ $feedback->url() }}" class="space-y-3 border-t border-border pt-4">
                        @csrf
                        <flux:input name="name" label="Your name" :value="old('name')" autocomplete="name" maxlength="40" required />
                        <flux:textarea name="response" label="Your feedback" rows="4" maxlength="2000" required>{{ old('response') }}</flux:textarea>
                        <flux:button type="submit" variant="primary">Send feedback</flux:button>
                    </form>
                @else
                    <p class="border-t border-border pt-4 text-sm text-muted-foreground">Comments are turned off for this review.</p>
                @endif
            </section>
        </main>
    </div>
</x-layouts.app>
