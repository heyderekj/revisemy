<x-home-section>
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">
        {{ $page['checklist_heading'] ?? ($page['label'].' checklist') }}
    </h2>
    @if (! empty($page['checklist_intro']))
        <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
            {{ $page['checklist_intro'] }}
        </p>
    @elseif (! empty($page['inputs']))
        <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
            ReviseMy runs these checks as optional second-opinion hints. Your marks stay authoritative.
        </p>
    @endif
    <ul class="mt-6 list-disc space-y-2 pl-5 text-[15px] leading-relaxed text-zinc-600">
        @foreach ($page['checklist'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</x-home-section>
