{{-- What the page says about the element: size, type, colours, spacing.
     Rows come from ElementTarget::inspectRows. A colour is the page's own,
     so its swatch takes it as a variable, never a class. --}}
@props(['target', 'open' => false])
@php($rows = \App\Support\ElementTarget::inspectRows($target))
@if ($rows !== [])
    <details {{ $attributes->class(['group rounded-lg bg-well text-xs']) }} @if ($open) open @endif data-element-inspect wire:ignore.self>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-2.5 py-1.5 font-medium text-zinc-600 [&::-webkit-details-marker]:hidden">
            Inspect
            <flux:icon.chevron-down variant="micro" class="size-3.5 text-zinc-400 transition group-open:rotate-180" />
        </summary>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 px-2.5 pb-2">
            @foreach ($rows as $row)
                <dt class="text-muted-foreground">{{ $row['label'] }}</dt>
                <dd class="flex min-w-0 items-center gap-1.5 font-mono text-zinc-700">
                    @isset($row['swatch'])
                        <span class="size-3 shrink-0 rounded-sm bg-(--rm-swatch) ring-1 ring-black/10" style="--rm-swatch: {{ $row['swatch'] }}" aria-hidden="true"></span>
                    @endisset
                    <span class="truncate select-all">{{ $row['value'] }}</span>
                </dd>
            @endforeach
        </dl>
    </details>
@endif
