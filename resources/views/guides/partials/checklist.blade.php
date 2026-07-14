<section class="mt-16 scroll-mt-8 sm:mt-20">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">{{ $page['checklist_heading'] ?? 'How it stays non-contradictory' }}</h2>
    <ul class="mt-6 list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
        @foreach ($page['checklist'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</section>
