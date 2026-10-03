{{-- Fieldnote newsletter, rendered as an inbox would show it. Sized in
     container units; positions line up with config/review-samples. --}}
<div class="@container relative aspect-[16/10] w-full overflow-hidden bg-well text-zinc-900">
    {{-- Envelope --}}
    <div class="absolute inset-x-[6%] top-[5%] flex items-center gap-[1.4cqw] rounded-[1cqw] bg-raised px-[2cqw] py-[1.2cqw]">
        <span class="flex size-[3.4cqw] shrink-0 items-center justify-center rounded-full bg-attention text-[1.4cqw] font-semibold text-attention-ink">F</span>
        <div class="min-w-0 text-[1.3cqw] leading-snug">
            <p><span class="font-semibold">Fieldnote Coffee</span> <span class="text-zinc-400">· 7:02 AM</span></p>
            <p class="truncate"><span class="font-medium">October beans are here</span> <span class="text-zinc-500">— October beans are here. Read more inside.</span></p>
        </div>
    </div>

    {{-- Body --}}
    <div class="absolute inset-x-[25%] bottom-0 top-[21%] rounded-t-[1cqw] bg-raised px-[2.4cqw] pt-[2.4cqw]">
        <div class="flex h-[17cqw] items-end justify-center gap-[1cqw] overflow-hidden rounded-[0.8cqw] bg-attention-soft pt-[2cqw]">
            @foreach ([['KOCHERE', 'Peach · jasmine', 'fill-attention'], ['HUILA', 'Cherry · cocoa', 'fill-done'], ['HOUSE', 'Caramel · nut', 'fill-zinc-700']] as [$name, $notes, $bag])
                <div class="h-[88%]">@include('components.review-mock.samples.bag', compact('name', 'notes', 'bag'))</div>
            @endforeach
        </div>
        <p class="mt-[1.8cqw] text-[2cqw] font-semibold tracking-tight">Three new coffees for fall</p>
        <p class="mt-[0.8cqw] text-[1.2cqw] leading-snug text-zinc-500">A washed Ethiopian, a honey-process Colombian and the house blend, roasted Monday.</p>
        <div class="mt-[2cqw] flex justify-center">
            <span class="rounded-full bg-zinc-900 px-[2.4cqw] py-[1cqw] text-[1.3cqw] font-medium text-zinc-50">Learn more</span>
        </div>
    </div>
</div>
