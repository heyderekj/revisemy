@props([
    'first' => false,
    // Skip the top rule + crosshairs; continue the previous section visually.
    'joined' => false,
    // Drop bottom padding so a media block can sit flush on the section edge.
    'flushBottom' => false,
])

{{-- A content section inside the framed column. Every section but the first is
     separated by a full-width rule that meets the main-column rails, with a
     crosshair pinned at each intersection. The page's top rule (sidebar + main)
     lives on the outer rm-rails frame. Horizontal padding comes from the
     column's --rm-pad and sits inside the border, so the rule reaches the rails
     while content is inset. Use joined to stack a section under the previous
     without a divider. --}}
<section {{ $attributes->class([
    'relative scroll-mt-8 px-[var(--rm-pad)]',
    'pt-8 sm:pt-10' => $first,
    'border-t border-zinc-200 py-16 sm:py-24' => ! $first && ! $joined && ! $flushBottom,
    'border-t border-zinc-200 pt-16 sm:pt-24' => ! $first && ! $joined && $flushBottom,
    'pt-6 sm:pt-8' => $joined,
    // One key only — duplicate keys overwrite in PHP arrays.
    'pb-16 sm:pb-24' => ($first || $joined) && ! $flushBottom,
]) }}>
    @unless ($first || $joined)
        <x-cross-mark left="0" top="0" />
        <x-cross-mark left="100%" top="0" />
    @endunless

    {{ $slot }}
</section>
