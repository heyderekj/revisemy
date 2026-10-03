{{-- My marks and Hints, as review/partials/sidebar draws them for the owner. --}}
@props(['marks' => [], 'hints' => []])

@php
    $awaiting = collect($marks)->where('status', \App\Models\Annotation::STATUS_RESOLVED)->count();
    $kinds = collect($hints)->pluck('severity')->unique();
@endphp

<div class="flex min-w-0 flex-col gap-3">
    <section class="rounded-2xl bg-card">
        <div class="flex items-center gap-2 px-3 py-3 @lg/mock:px-4">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <flux:heading size="sm">My marks</flux:heading>
                <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-600">{{ count($marks) }}</span>
            </div>
            @if ($awaiting > 0)
                <span class="inline-flex h-7 shrink-0 items-center gap-1 rounded-full bg-done-soft px-2.5 text-xs font-medium text-done-ink">
                    <flux:icon.check variant="micro" class="size-3.5" /> Verify all {{ $awaiting }}
                </span>
            @endif
            <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 rotate-180 text-zinc-400" />
        </div>
        <div class="px-3 pb-3 @lg/mock:px-4 @lg/mock:pb-4">
            @if ($awaiting > 0)
                <p class="mb-3 text-xs text-muted-foreground">Your agent fixed {{ $awaiting }} {{ $awaiting === 1 ? 'mark' : 'marks' }}. Check each one, then verify or reopen.</p>
            @endif
            <ul class="space-y-2.5">
                @foreach ($marks as $i => $mark)
                    <x-review-mock.mark-card :mark="$mark" :number="$i + 1" />
                @endforeach
            </ul>
        </div>
    </section>

    @if (count($hints) > 0)
        <section class="rounded-2xl bg-card">
            <div class="flex items-center gap-2 px-3 py-3 @lg/mock:px-4">
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <flux:heading size="sm">Hints</flux:heading>
                    <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-600">{{ count($hints) }}</span>
                </div>
                <flux:icon.arrow-path variant="micro" class="size-4 shrink-0 text-zinc-500" />
                <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 rotate-180 text-zinc-400" />
            </div>
            <div class="px-3 pb-3 @lg/mock:px-4 @lg/mock:pb-4">
                <p class="mb-3 text-xs text-muted-foreground">Suggestions until you accept them.</p>
                @if ($kinds->count() > 1)
                    <div class="mb-3 flex flex-wrap items-center gap-1">
                        <span class="inline-flex h-7 items-center rounded-full bg-raised px-2.5 text-xs font-medium text-zinc-900 shadow-xs ring-1 ring-black/[0.06]">All</span>
                        @foreach ($kinds as $kind)
                            <span class="inline-flex h-7 items-center rounded-full px-2.5 text-xs font-medium text-zinc-500">{{ \App\Models\Annotation::allSeverityLabels()[$kind] ?? ucfirst($kind) }}</span>
                        @endforeach
                    </div>
                @endif
                <ul class="space-y-2.5">
                    @foreach ($hints as $i => $hint)
                        <x-review-mock.hint :hint="$hint" :number="$i + 1" />
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</div>
