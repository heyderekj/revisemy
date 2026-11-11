@props(['target', 'open' => false, 'screenshot' => null, 'referenceAction' => null, 'selectedReferences' => [], 'markId' => null])
@php
    $rows = \App\Support\ElementTarget::inspectRows($target);
    $canReference = $referenceAction && $screenshot?->hasElementTargets() && isset($target['index']);
    $selected = array_map(\App\Support\ElementTarget::referenceKey(...), $selectedReferences);
@endphp
@if ($rows !== [])
    <details {{ $attributes->class(['group rounded-lg bg-well text-xs']) }} @if ($open) open @endif data-element-inspect wire:ignore.self>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 font-medium text-zinc-700 hover:text-zinc-900 [&::-webkit-details-marker]:hidden">
            Inspect
            <flux:icon.chevron-down variant="micro" class="size-3.5 text-zinc-400 transition group-open:rotate-180" />
        </summary>
        <div class="border-t border-border p-1">
            @foreach ($rows as $row)
                @continue(isset($row['group']))
                @include('components.element-inspect-row')
            @endforeach
            @php($extra = collect($rows)->filter(fn ($row) => isset($row['group']))->groupBy('group'))
            @if ($extra->isNotEmpty())
                <details class="border-t border-border" data-inspect-more wire:ignore.self>
                    <summary class="cursor-pointer rounded-md px-2 py-2 text-muted-foreground hover:bg-chip">More properties</summary>
                    @foreach ($extra as $group => $groupRows)
                        <p class="px-2 pt-2 pb-0.5 font-medium text-muted-foreground">{{ $group }}</p>
                        @foreach ($groupRows as $row)
                            @include('components.element-inspect-row')
                        @endforeach
                    @endforeach
                </details>
            @endif
        </div>
    </details>
@endif
