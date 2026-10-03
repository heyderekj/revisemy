{{-- The Fieldnote hero copy alone, for the before/after pair. --}}
@props(['headline'])

<div class="@container relative aspect-[16/8] overflow-hidden rounded-md bg-raised ring-1 ring-black/[0.06]">
    <div class="absolute inset-x-[7%] top-[14%]">
        <p class="text-[4cqw] font-medium uppercase tracking-[0.14em] text-attention-ink">Small-batch roasters</p>
        <p class="mt-[1.5cqw] text-[7.5cqw] font-semibold leading-[1.05] tracking-tight text-zinc-900">{{ $headline }}</p>
    </div>
</div>
