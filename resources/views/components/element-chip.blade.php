{{-- The element a mark is on, as the page named it: its kind and its words.
     Colours live in Annotation::ELEMENT_CHIP; the inline review draws the
     same chip in mcp/partials/element-chip. --}}
@props(['target'])
@php($words = (string) (($target['label'] ?? '') !== '' ? $target['label'] : ($target['text'] ?? '')))
<span
    {{ $attributes->class(['inline-flex min-w-0 max-w-full items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] leading-4', \App\Models\Annotation::ELEMENT_CHIP]) }}
    title="{{ $target['selector'] ?? '' }}"
    data-element-chip
>
    <flux:icon.cursor-arrow-rays variant="micro" class="size-3 shrink-0 text-zinc-500" />
    <span class="shrink-0 font-semibold">{{ $target['kind'] ?? 'Element' }}</span>
    @if ($words !== '')
        <span class="truncate text-zinc-500">“{{ \Illuminate\Support\Str::limit($words, 48) }}”</span>
    @endif
</span>
