<section class="rm-fade-up mt-10 sm:mt-12">
    <div class="flex items-center gap-3">
        @if (! empty($page['mark_icon']))
            <x-mark-type-icon :type="$page['mark_icon']" />
        @else
            <x-use-case-icon :name="$page['icon']" size="lg" />
        @endif
        <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">{{ $page['label'] }}</p>
    </div>
    <h1 class="mt-5 max-w-xl text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
        {{ $page['headline'] }}
    </h1>
    <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
        {{ $page['subheadline'] }}
    </p>
</section>
