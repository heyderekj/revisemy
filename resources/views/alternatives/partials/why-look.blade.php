<x-home-section>
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">
        Why people look for a {{ $page['competitor'] }} alternative
    </h2>
    <ul class="mt-6 list-disc space-y-2.5 pl-5 text-[15px] leading-relaxed text-zinc-600">
        @foreach ($page['why_look'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</x-home-section>
