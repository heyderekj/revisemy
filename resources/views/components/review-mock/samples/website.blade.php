{{-- Fieldnote Coffee home page. Sized in container units so it scales like
     a screenshot. Positions line up with the marks in config/review-samples. --}}
@php($beans = [['Kochere', 'Peach · jasmine', 'fill-attention', '$19'], ['Huila', 'Cherry · cocoa', 'fill-done', '$17'], ['House', 'Caramel · nut', 'fill-zinc-700', '$15']])
<div class="@container relative aspect-[16/10] w-full overflow-hidden bg-raised text-zinc-900">
    {{-- Nav --}}
    <div class="absolute inset-x-[6%] top-[5%] flex items-center justify-between">
        <span class="flex items-center gap-[0.8cqw] text-[1.9cqw] font-semibold tracking-tight">
            <span class="size-[2.2cqw] rounded-full bg-attention"></span>Fieldnote
        </span>
        <span class="flex gap-[2.2cqw] text-[1.35cqw] text-zinc-500">
            <span>Shop</span><span>Subscribe</span><span>Cafés</span><span>Journal</span><span>About</span>
        </span>
    </div>

    {{-- Hero copy --}}
    <div class="absolute left-[9%] top-[20%] w-[46%]">
        <p class="text-[1.2cqw] font-medium uppercase tracking-[0.14em] text-attention-ink">Small-batch roasters</p>
        <p class="mt-[0.8cqw] text-[3.9cqw] font-semibold leading-[1.05] tracking-tight">We roast coffee in Des&nbsp;Moines.</p>
    </div>
    <p class="absolute left-[9%] top-[43%] w-[44%] text-[1.4cqw] leading-snug text-zinc-500">Fresh beans every two weeks, roasted the morning they ship.</p>
    <div class="absolute left-[9%] top-[50%] flex items-center gap-[1.6cqw]">
        <span class="rounded-full bg-zinc-200 px-[1.8cqw] py-[0.9cqw] text-[1.3cqw] font-medium text-zinc-700">Start a subscription</span>
        <span class="text-[1.3cqw] font-medium text-zinc-900 underline underline-offset-2">Browse beans</span>
    </div>

    {{-- Hero photo: a mug on a saucer, beans on the table --}}
    <div class="absolute left-[60%] top-[16%] h-[42%] w-[34%] overflow-hidden rounded-[1.2cqw] bg-attention-soft">
        <img
            src="{{ asset('images/fieldnote-coffee.webp') }}"
            alt=""
            width="768"
            height="576"
            class="size-full object-cover"
            decoding="async"
        >
        <p class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-[6%] pb-[5%] pt-[8%] text-[1.15cqw] text-white">Kochere, washed. Light roast.</p>
    </div>

    {{-- Product cards --}}
    <div class="absolute left-[8%] right-[8%] top-[66%] grid grid-cols-3 gap-[2cqw]">
        @foreach ($beans as [$name, $notes, $bag, $price])
            <div>
                <div class="flex h-[11.5cqw] items-end justify-center rounded-[1cqw] bg-well pt-[1.2cqw]">
                    @include('components.review-mock.samples.bag', ['name' => strtoupper($name), 'notes' => $notes, 'bag' => $bag])
                </div>
                <div class="mt-[0.8cqw] flex justify-between text-[1.3cqw]">
                    <span class="font-medium">{{ $name }}{{ $name === 'House' ? ' blend' : '' }}</span>
                    <span class="tabular-nums text-zinc-500">{{ $price }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
