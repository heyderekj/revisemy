@props([
    'first' => false,
    // Continue the previous section visually.
    'joined' => false,
    // Drop bottom padding so a media block can sit flush on the section edge.
    'flushBottom' => false,
])

{{-- A content section inside the page column. Sections are told apart by
     space, not rules. Use joined to stack a section closer under the previous
     one. Horizontal padding comes from the column's --rm-pad. --}}
<section {{ $attributes->class([
    'relative scroll-mt-8 px-[var(--rm-pad)]',
    'pt-8 sm:pt-10' => $first,
    'py-12 sm:py-16' => ! $first && ! $joined && ! $flushBottom,
    'pt-12 sm:pt-16' => ! $first && ! $joined && $flushBottom,
    'pt-6 sm:pt-8' => $joined,
    // One key only — duplicate keys overwrite in PHP arrays.
    'pb-12 sm:pb-16' => ($first || $joined) && ! $flushBottom,
]) }}>
    {{ $slot }}
</section>
