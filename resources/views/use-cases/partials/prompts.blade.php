<section class="mt-16 scroll-mt-8 sm:mt-20">
    <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Sample prompts</h2>
    <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
        Try saying these to your agent after connecting MCP from the homepage.
    </p>
    <ul class="mt-6 space-y-3">
        @foreach ($page['prompts'] as $prompt)
            <li>
                <x-sample-prompt :text="$prompt" />
            </li>
        @endforeach
    </ul>
</section>
