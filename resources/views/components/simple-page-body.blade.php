{{-- The words of a <x-simple-page>, drawn inside whichever frame it uses. --}}
@if ($eyebrow)
    <p class="text-sm font-medium text-muted-foreground">{{ $eyebrow }}</p>
@endif
<h1 class="mt-2 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">{{ $heading }}</h1>
@if ($updated)
    <p class="mt-3 text-sm text-muted-foreground">Last updated {{ $updated }}</p>
@endif
<div class="mt-6 max-w-2xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
    {{ $slot }}
</div>
