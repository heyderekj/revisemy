<section class="mt-16 scroll-mt-8 sm:mt-20">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">How ReviseMy fits</h2>
    <p class="mt-4 text-[15px] leading-relaxed text-zinc-600">
        {{ $page['loop'] }}
    </p>
    @if (! empty($page['review_type']) && ! empty($page['inputs']))
        <p class="mt-4 text-sm text-zinc-500">
            Review type: <code class="font-mono text-[13px] text-rose-600">{{ $page['review_type'] }}</code>
            · Exactly one ingest source per review
        </p>
    @elseif (! empty($page['review_type']))
        <p class="mt-4 text-sm text-zinc-500">
            Review type: <code class="font-mono text-[13px] text-rose-600">{{ $page['review_type'] }}</code>
        </p>
    @endif
</section>
