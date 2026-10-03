{{-- A still of the review page for marketing: same header, canvas and
     sidebar as /r/{token}, drawn from a fictional sample in
     config/review-samples.php. No Livewire and nothing to press — the whole
     thing is inert and reads as one image. Labels and colours come from
     App\Models\Annotation, so it can't drift from the real page.

     It sits on a dot-grid stage, padded at the sides and top and cut off at
     the bottom, so it reads as a picture of the app rather than the app.
     Anything in the slot sits on the stage above the page. --}}
@props([
    'sample' => 'website',
    // Pop the marks in one by one, for the home hero.
    'animate' => false,
])

@php
    $data = config("review-samples.{$sample}");
    $delay = fn (int $i) => $animate ? 'animation-delay: '.(1.1 + $i * 0.2).'s;' : '';
@endphp

@if ($data)
    <figure {{ $attributes->class(['rm-dot-grid m-0 overflow-hidden rounded-3xl bg-card px-3 pt-5 sm:px-8 sm:pt-8 lg:px-10 lg:pt-10']) }} role="img" aria-label="{{ $data['label'] }}">
        {{ $slot }}
        <div inert @class([
            '@container/mock overflow-hidden rounded-t-2xl bg-background text-left shadow-[0_24px_60px_-32px_rgba(24,24,27,0.35)] ring-1 ring-black/[0.06] select-none',
            'rm-hero-loop-review' => $animate,
        ])>
            {{-- Header, as review/partials/header --}}
            <div class="flex items-center gap-2 border-b border-border px-3 py-2.5 @lg/mock:gap-3 @lg/mock:px-4">
                <x-revisemy-logo size="sm" />
                <span class="hidden text-lg font-semibold tracking-tight text-zinc-900 @lg/mock:inline">Review</span>
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <span class="inline-flex shrink-0 items-center rounded-md bg-chip px-1.5 py-0.5 text-xs font-medium tabular-nums text-zinc-600">Pass {{ $data['pass'] }}</span>
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-md bg-chip px-1.5 py-0.5 text-xs font-medium text-zinc-600 @max-sm/mock:hidden">
                        @if (! empty($data['captured']))
                            <flux:icon.link variant="micro" class="size-3 shrink-0 text-zinc-400" />
                        @endif
                        {{ $data['source'] }}
                    </span>
                    <span class="hidden min-w-0 truncate text-sm text-zinc-500 @5xl/mock:inline">{{ $data['title'] }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-1.5">
                    <flux:button size="sm" variant="ghost" icon="link" class="!bg-chip @max-xl/mock:!hidden" tabindex="-1">Share</flux:button>
                    <flux:button size="sm" variant="ghost" icon="view-columns" class="!bg-chip @max-3xl/mock:!hidden" tabindex="-1">Board</flux:button>
                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" class="!bg-chip @max-md/mock:!hidden" tabindex="-1">Changes</flux:button>
                    <flux:button size="sm" variant="primary" icon="check" tabindex="-1">Approve</flux:button>
                </div>
            </div>

            <div class="grid gap-3 bg-canvas p-3 @lg/mock:p-4 @2xl/mock:grid-cols-[minmax(0,1fr)_15rem] @5xl/mock:grid-cols-[minmax(0,1fr)_18rem]">
                {{-- Canvas, as review/partials/canvas --}}
                <div class="min-w-0 rounded-2xl bg-card p-3 @lg/mock:p-4">
                    @if (! empty($data['context']))
                        <div class="mb-3 grid gap-1 @lg/mock:mb-4 @lg/mock:grid-cols-[8rem_1fr] @lg/mock:gap-3">
                            <flux:heading size="sm">What to look at</flux:heading>
                            <p class="text-sm text-zinc-600">{{ $data['context'] }}</p>
                        </div>
                    @endif
                    <div class="relative overflow-hidden rounded-lg ring-1 ring-black/[0.06]">
                        @include("components.review-mock.samples.{$sample}")

                        @foreach ($data['hints'] as $i => $hint)
                            <div class="pointer-events-none absolute z-[6] -translate-x-1/2 -translate-y-1/2" style="left: {{ $hint['x'] * 100 }}%; top: {{ $hint['y'] * 100 }}%;">
                                <span @class(['flex h-6 min-w-6 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-raised px-0.5 text-xs font-semibold text-sky-700 shadow-sm', 'rm-review-mock-pop' => $animate]) style="{{ $delay(count($data['marks']) + $i) }}">S{{ $i + 1 }}</span>
                            </div>
                        @endforeach

                        @foreach ($data['marks'] as $i => $mark)
                            @php($state = match ($mark['status']) {
                                \App\Models\Annotation::STATUS_VERIFIED => 'opacity-40',
                                \App\Models\Annotation::STATUS_RESOLVED => 'opacity-70',
                                default => '',
                            })
                            @if (isset($mark['w']))
                                <div @class(['pointer-events-none absolute z-[8]', $state, 'rm-review-mock-pop' => $animate]) style="left: {{ $mark['x'] * 100 }}%; top: {{ $mark['y'] * 100 }}%; width: {{ $mark['w'] * 100 }}%; height: {{ $mark['h'] * 100 }}%; {{ $delay($i) }}">
                                    <div class="absolute inset-0 rounded-md border-2 border-key/80 bg-key/10"></div>
                                    <span class="absolute -left-2 -top-2 z-[9] flex h-6 min-w-6 items-center justify-center rounded-full px-0.5 text-xs font-semibold shadow-sm ring-2 ring-raised {{ (new \App\Models\Annotation)->markerClass() }}">M{{ $i + 1 }}</span>
                                </div>
                            @else
                                <div class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-1/2" style="left: {{ $mark['x'] * 100 }}%; top: {{ $mark['y'] * 100 }}%;">
                                    <span @class(['flex h-7 min-w-7 items-center justify-center rounded-full px-1 text-xs font-semibold shadow-lg ring-2 ring-raised', (new \App\Models\Annotation)->markerClass(), $state, 'rm-review-mock-pop' => $animate]) style="{{ $delay($i) }}">M{{ $i + 1 }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-3 text-center text-xs text-muted-foreground">Drag to mark a region, or click for a point.</p>
                </div>

                {{-- Cropped to the canvas's height, like a screenshot of the page. --}}
                <div class="relative max-h-96 min-w-0 overflow-hidden @2xl/mock:max-h-none">
                    <div class="@2xl/mock:absolute @2xl/mock:inset-0">
                        <x-review-mock.sidebar :marks="$data['marks']" :hints="$data['hints']" />
                    </div>
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-canvas to-transparent"></div>
                </div>
            </div>
        </div>
    </figure>
@endif
