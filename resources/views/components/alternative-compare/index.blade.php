{{-- The same note, two endings: where it goes in the other tool (left,
     drawn generic and grey) and in ReviseMy (right, the review page's own
     pieces). Copy comes from the page's `compare` block in
     config/alternatives.php; the left pane is endings/{ending}.blade.php. --}}
@props(['page'])
@use('App\Models\Annotation')

@php
    $compare = $page['compare'] ?? null;
    $mark = fn (string $status) => ['severity' => Annotation::SEVERITY_MUST_FIX, 'status' => $status, 'note' => $compare['note'] ?? ''];
@endphp

@if ($compare)
    <figure {{ $attributes->class(['rm-dot-grid m-0 rounded-3xl bg-card p-3 sm:p-6 lg:p-8']) }} role="img" aria-label="The same feedback in {{ $page['competitor'] }} and in ReviseMy. {{ $compare['them_end'] }} {{ $compare['us_end'] ?? '' }}">
        <div inert class="grid select-none gap-3 text-left sm:gap-4 md:grid-cols-2">
            {{-- Theirs --}}
            <div class="flex min-w-0 flex-col gap-3 rounded-2xl bg-background p-3 ring-1 ring-black/[0.06] sm:p-4">
                <div class="flex items-center gap-2">
                    <x-use-case-icon :name="$page['icon']" size="sm" />
                    <p class="text-sm font-medium text-zinc-500">With {{ $page['competitor'] }}</p>
                </div>

                @include("components.alternative-compare.endings.{$compare['ending']}")

                <x-alternative-compare.end>{{ $compare['them_end'] }}</x-alternative-compare.end>
            </div>

            {{-- Ours --}}
            <div class="flex min-w-0 flex-col rounded-2xl bg-background p-3 shadow-[0_24px_60px_-32px_rgba(24,24,27,0.35)] ring-1 ring-black/[0.06] sm:p-4">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <x-revisemy-logo size="sm" />
                    <p class="text-sm font-semibold text-zinc-900">With ReviseMy</p>
                    <span class="ml-auto inline-flex items-center gap-1 rounded-md bg-raised/80 px-1.5 py-0.5 font-mono text-[11px] text-zinc-500 ring-1 ring-black/[0.05]">
                        <flux:icon.check variant="micro" class="size-3 text-done" /> agent · create_review
                    </span>
                </div>

                <x-alternative-compare.shot>
                    <div class="pointer-events-none absolute rm-review-mock-pop" style="left: 7%; top: 17%; width: 50%; height: 23%; animation-delay: .6s;">
                        <div class="absolute inset-0 rounded-md border-2 border-key/80 bg-key/10"></div>
                        <span class="absolute -left-2 -top-2 flex h-6 min-w-6 items-center justify-center rounded-full px-0.5 text-xs font-semibold shadow-sm ring-2 ring-raised {{ (new Annotation)->markerClass() }}">M1</span>
                    </div>
                </x-alternative-compare.shot>
                <ul class="mt-2.5">
                    <x-review-mock.mark-card :mark="$mark(Annotation::STATUS_OPEN)" :number="1" />
                </ul>

                <x-alternative-compare.arrow />

                <div class="flex gap-2.5 rounded-xl bg-canvas p-2.5">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-raised text-zinc-700 shadow-xs ring-1 ring-black/[0.06]"><flux:icon.cpu-chip variant="micro" class="size-3.5" /></span>
                    <div class="min-w-0 space-y-1.5">
                        <p class="inline-flex flex-wrap items-center gap-x-1.5 gap-y-0.5 rounded-md bg-raised/80 px-2 py-1 font-mono text-[11px] text-zinc-500 ring-1 ring-black/[0.05]">
                            <flux:icon.check variant="micro" class="size-3.5 text-done" />
                            revisemy · get_review
                            <span class="text-zinc-400">→ next_action: <span class="text-zinc-700">apply_marks</span></span>
                        </p>
                        <p class="text-sm leading-relaxed text-zinc-700">{{ $compare['agent_reply'] ?? 'Rewrote the headline around the subscription. Resolved M1 with an after shot for you to check.' }}</p>
                    </div>
                </div>

                <x-alternative-compare.arrow />

                <ul>
                    <x-review-mock.mark-card :mark="$mark(Annotation::STATUS_RESOLVED)" :number="1" />
                </ul>
                <div class="mb-3 mt-2 grid grid-cols-2 gap-2">
                    <div class="min-w-0">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-400">Before</p>
                        <x-alternative-compare.headline-crop headline="We roast coffee in Des Moines." />
                    </div>
                    <div class="min-w-0">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-done-ink">After</p>
                        <x-alternative-compare.headline-crop headline="Fresh beans at your door, every two weeks." />
                    </div>
                </div>

                <x-alternative-compare.end done>{{ $compare['us_end'] ?? 'Only you can mark it verified, after comparing before and after.' }}</x-alternative-compare.end>
            </div>
        </div>
    </figure>
@endif
