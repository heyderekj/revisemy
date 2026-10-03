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
        <svg viewBox="0 0 120 90" preserveAspectRatio="xMidYMid slice" class="size-full" aria-hidden="true">
            <rect y="60" width="120" height="30" class="fill-attention/25" />
            <path d="M40 22c-3-4 3-7 0-11M50 20c-3-4 3-7 0-11M60 22c-3-4 3-7 0-11" class="stroke-attention-ink/40" stroke-width="1.6" fill="none" stroke-linecap="round" />
            <ellipse cx="50" cy="66" rx="32" ry="6.5" class="fill-zinc-50" />
            <ellipse cx="50" cy="65" rx="22" ry="3.5" class="fill-black/5" />
            <path d="M30 32h40v22a10 10 0 0 1-10 10H40a10 10 0 0 1-10-10Z" class="fill-zinc-50" />
            <path d="M70 37h4a7 7 0 0 1 0 14h-4" class="stroke-zinc-50" stroke-width="3.2" fill="none" />
            <ellipse cx="50" cy="32" rx="20" ry="3.6" class="fill-attention-ink" />
            @foreach ([[92, 70, 20], [101, 76, -30], [86, 80, 60], [106, 66, 10]] as [$x, $y, $r])
                <g transform="translate({{ $x }} {{ $y }}) rotate({{ $r }})">
                    <ellipse rx="4.2" ry="2.8" class="fill-attention-ink" />
                    <path d="M-3.4 0q1.7-1 3.4 0t3.4 0" class="stroke-attention-soft" stroke-width="0.7" fill="none" />
                </g>
            @endforeach
        </svg>
        <p class="absolute bottom-[6%] left-[6%] text-[1.15cqw] text-zinc-500">Kochere, washed. Light roast.</p>
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
