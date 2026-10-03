{{-- One pitch deck slide. Sized in container units; positions line up
     with config/review-samples. --}}
<div class="@container relative aspect-[16/9] w-full overflow-hidden bg-raised text-zinc-900">
    <p class="absolute left-[8%] top-[7%] text-[1.2cqw] font-medium uppercase tracking-[0.14em] text-zinc-400">Traction</p>
    <p class="absolute left-[8%] top-[13%] w-[58%] text-[3.6cqw] font-semibold leading-[1.08] tracking-tight">Customers are sticking around longer</p>

    <ul class="absolute left-[8%] top-[42%] w-[40%] space-y-[1.6cqw] text-[1.5cqw] leading-snug text-zinc-600">
        <li class="flex gap-[1cqw]"><span class="mt-[0.6cqw] size-[0.8cqw] shrink-0 rounded-full bg-zinc-400"></span>Monthly retention up from 22% to 64%</li>
        <li class="flex gap-[1cqw]"><span class="mt-[0.6cqw] size-[0.8cqw] shrink-0 rounded-full bg-zinc-400"></span>Onboarding cut to one screen in March</li>
        <li class="flex gap-[1cqw]"><span class="mt-[0.6cqw] size-[0.8cqw] shrink-0 rounded-full bg-zinc-400"></span>No paid acquisition yet</li>
    </ul>

    {{-- Chart --}}
    <div class="absolute bottom-[16%] left-[55%] right-[7%] top-[36%] flex items-end gap-[1.4cqw] border-b border-border">
        @foreach ([22, 26, 31, 44, 57, 64] as $i => $value)
            <div @class(['flex-1 rounded-t-[0.5cqw]', 'bg-key' => $i === 5, 'bg-zinc-200' => $i !== 5]) style="height: {{ $value * 1.4 }}%"></div>
        @endforeach
    </div>

    <p class="absolute bottom-[8%] left-[8%] text-[0.9cqw] text-zinc-400">Source: product analytics, cohorts Jan–Jun. Retention is 30-day active.</p>
</div>
