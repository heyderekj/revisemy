{{-- The element a mark is on: kind and words, as on the web
     (components/element-chip). $source is the Alpine expression holding it:
     a pin's element, or the composer's target. --}}
<span class="inline-flex min-w-0 max-w-full items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] leading-4 {{ \App\Models\Annotation::ELEMENT_CHIP }} {{ $class ?? '' }}"
    x-show="{{ $source }}" :title="{{ $source }} && {{ $source }}.selector" data-element-chip>
    <svg viewBox="0 0 16 16" fill="currentColor" class="size-3 shrink-0 text-zinc-500" aria-hidden="true"><path d="M3.3 2.3a.75.75 0 0 1 .8-.16l9 3.75a.75.75 0 0 1-.05 1.4l-3.6 1.2-1.2 3.6a.75.75 0 0 1-1.4.05l-3.75-9a.75.75 0 0 1 .2-.84Z"/></svg>
    <span class="shrink-0 font-semibold" x-text="{{ $source }} && {{ $source }}.kind"></span>
    <span class="truncate text-zinc-500" x-show="{{ $source }} && ({{ $source }}.label || {{ $source }}.text)"
        x-text="{{ $source }} && '“' + ({{ $source }}.label || {{ $source }}.text || '').slice(0, 48) + '”'"></span>
</span>
