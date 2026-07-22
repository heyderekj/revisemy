{{-- Stylized website-hero wireframe for marketing previews. --}}
@props([
    'dashed' => false,
])

<div {{ $attributes->class([
    'absolute inset-3 flex flex-col bg-white p-2.5 sm:p-3',
    'border border-dashed border-zinc-300' => $dashed,
    'border border-zinc-200' => ! $dashed,
]) }}>
    {{-- Nav --}}
    <div class="flex items-center gap-2 border-b border-zinc-100 pb-2">
        <span class="size-2.5 shrink-0 bg-[var(--color-accent)] ring-1 ring-black/10"></span>
        <span class="h-1 w-8 bg-zinc-200"></span>
        <div class="ml-auto flex items-center gap-1.5">
            <span class="hidden h-1 w-6 bg-zinc-100 sm:block"></span>
            <span class="hidden h-1 w-5 bg-zinc-100 sm:block"></span>
            <span class="h-1 w-5 bg-zinc-100"></span>
            <span class="h-3 w-10 bg-[var(--color-accent)] ring-1 ring-black/10"></span>
        </div>
    </div>

    {{-- Hero body --}}
    <div class="mt-2.5 grid min-h-0 flex-1 grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] gap-2.5">
        <div class="flex min-w-0 flex-col justify-center gap-1.5">
            <span class="h-2 w-[88%] bg-zinc-800/80"></span>
            <span class="h-2 w-[62%] bg-zinc-800/80"></span>
            <span class="mt-1 h-1 w-[92%] bg-zinc-200"></span>
            <span class="h-1 w-[78%] bg-zinc-200"></span>
            <span class="mt-2 h-3.5 w-14 bg-[var(--color-accent)] ring-1 ring-black/10"></span>
        </div>
        <div class="relative min-h-[3.5rem] bg-zinc-100 ring-1 ring-zinc-200">
            <div class="absolute inset-2 border border-dashed border-zinc-200/90 bg-zinc-50/80"></div>
            <div class="absolute bottom-2 left-2 right-2 h-1.5 bg-zinc-200/80"></div>
        </div>
    </div>
</div>
