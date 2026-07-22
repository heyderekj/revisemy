{{-- Miniature owner board — denser than the live UI so it reads at guide width. --}}
@php
    $columns = [
        [
            'label' => 'Open',
            'owner' => 'You',
            'count' => 2,
            'icon' => 'flag',
            'droppable' => true,
            'empty' => null,
            'cards' => [
                ['n' => 3, 'intent' => 'Nice to have', 'status' => 'Open', 'statusClass' => 'bg-zinc-100 text-zinc-600', 'body' => 'Title, OG, and load aren’t in the PNG.'],
                ['n' => 1, 'intent' => 'Must fix', 'status' => 'Open', 'statusClass' => 'bg-zinc-100 text-zinc-600', 'body' => 'Above the fold: offer + next step clear?'],
            ],
        ],
        [
            'label' => 'In progress',
            'owner' => 'Agent',
            'count' => 0,
            'icon' => 'cpu-chip',
            'droppable' => false,
            'empty' => 'Agent starts fixes here',
            'cards' => [],
        ],
        [
            'label' => 'Resolved',
            'owner' => 'You or agent',
            'count' => 0,
            'icon' => 'check-circle',
            'droppable' => true,
            'empty' => 'Drop to mark resolved',
            'cards' => [],
        ],
        [
            'label' => 'Verified',
            'owner' => 'You',
            'count' => 1,
            'icon' => 'shield-check',
            'droppable' => true,
            'empty' => null,
            'cards' => [
                ['n' => 2, 'intent' => 'Keep this', 'status' => 'Verified', 'statusClass' => 'bg-emerald-100 text-emerald-800', 'body' => 'This sounds good.'],
            ],
        ],
    ];
@endphp

<section
    class="rm-board-preview flex h-[320px] flex-col overflow-hidden border-t border-zinc-200 bg-[var(--color-canvas)]"
    aria-label="Preview of the ReviseMy owner board"
>
    {{-- Header — mirrors review-board chrome at ~0.75 scale --}}
    <div class="flex shrink-0 items-center gap-1.5 border-b border-zinc-200/80 bg-zinc-50/90 px-2.5 py-1.5" aria-hidden="true">
        <img src="{{ \App\Support\BrandAssets::appIconUrl() }}" alt="" width="16" height="16" class="size-4 shrink-0" decoding="async">
        <span class="text-[12px] font-semibold tracking-tight text-zinc-900">Board</span>
        <span class="inline-flex items-center rounded-md border border-zinc-200 bg-white px-1 py-px text-[7px] font-medium tabular-nums text-zinc-600">Pass 1</span>
        <span class="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-1 py-px text-[7px] font-medium uppercase tracking-wide text-sky-700">Website</span>
        <span class="min-w-0 flex-1 truncate text-[8px] text-zinc-500">ReviseMy hero — simplify header tabs</span>
        <span class="hidden shrink-0 text-[7px] tabular-nums text-zinc-500 sm:inline">1/3</span>
        <div class="hidden h-0.5 w-10 shrink-0 overflow-hidden rounded-full bg-zinc-200/80 sm:block">
            <div class="h-full w-1/3 rounded-full bg-emerald-500"></div>
        </div>
        <span class="shrink-0 rounded-md bg-zinc-100 px-1.5 py-0.5 text-[9px] font-medium text-zinc-600">← Review</span>
    </div>

    {{-- Four columns like desktop board — compact cards, short empty states --}}
    <div class="grid min-h-0 flex-1 grid-cols-4 gap-1.5 bg-zinc-100/80 p-1.5 sm:gap-2 sm:p-2" aria-hidden="true">
        @foreach ($columns as $i => $column)
            <div
                class="rm-board-preview-col flex min-h-0 min-w-0 flex-col rounded-lg border p-1.5 sm:rounded-xl sm:p-2 {{ $column['droppable'] ? 'border-zinc-200 bg-white/80' : 'border-dashed border-zinc-200/90 bg-zinc-50/80' }}"
                style="--i: {{ $i }}"
            >
                <div class="mb-1 flex items-start justify-between gap-1">
                    <div class="flex min-w-0 items-start gap-1">
                        <span class="inline-flex size-4 shrink-0 items-center justify-center rounded bg-zinc-100 text-zinc-600">
                            <flux:icon :name="$column['icon']" variant="micro" class="size-2.5" />
                        </span>
                        <div class="min-w-0 leading-tight">
                            <p class="truncate text-[8px] font-semibold text-zinc-900 sm:text-[9px]">{{ $column['label'] }}</p>
                            <p class="truncate text-[7px] font-medium uppercase tracking-wide text-zinc-400">{{ $column['owner'] }}</p>
                        </div>
                    </div>
                    <span class="flex h-3.5 min-w-3.5 shrink-0 items-center justify-center rounded-full bg-zinc-100 px-1 text-[7px] font-semibold tabular-nums text-zinc-700">{{ $column['count'] }}</span>
                </div>

                <div class="flex flex-1 flex-col gap-1">
                    @forelse ($column['cards'] as $card)
                        <div class="rounded-md border border-zinc-200 bg-white p-1.5 shadow-sm sm:rounded-lg">
                            <div class="mb-0.5 flex flex-wrap items-center gap-0.5">
                                <span class="flex h-3 min-w-3 items-center justify-center rounded-full bg-accent px-0.5 text-[7px] font-semibold text-ink">M{{ $card['n'] }}</span>
                                <span class="truncate text-[7px] text-zinc-500">{{ $card['intent'] }}</span>
                                <span class="rounded-full px-1 py-px text-[6px] font-medium {{ $card['statusClass'] }}">{{ $card['status'] }}</span>
                            </div>
                            <p class="line-clamp-2 text-[8px] leading-snug text-zinc-600">{{ $card['body'] }}</p>
                        </div>
                    @empty
                        <div class="flex flex-1 items-center justify-center rounded-md border border-dashed border-zinc-200 px-1 py-3 text-center text-[7px] leading-snug text-zinc-400 sm:rounded-lg">
                            {{ $column['empty'] }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</section>
