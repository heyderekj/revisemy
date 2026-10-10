@php
    use App\Support\TrustFacts;

    // Read from this install's setup, so the page can't promise what isn't so.
    // The markdown twin (/security.md) reads the same copy.
    $page = TrustFacts::page();
@endphp

<x-layouts.marketing title="Privacy and security — ReviseMy" description="Who can open a ReviseMy review, where your work goes, how long it’s kept, and how to delete it. Open source, so you can check every line." :keywords="['design review privacy', 'secure design feedback', 'MCP security', 'review link privacy']" fathom="Security">
    <x-marketing-hero eyebrow="Privacy and security" icon="lock-closed" :headline="$page['headline']" :subheadline="$page['lead']" />

    <x-home-section id="who">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Who can open a review</h2>
        <div class="mt-8 grid grid-cols-1 gap-x-8 gap-y-9 min-[30rem]:grid-cols-2">
            @foreach ($page['who'] as $item)
                <article>
                    <x-use-case-icon :name="$item['icon']" />
                    <h3 class="mt-3 text-sm font-semibold text-zinc-900">{{ $item['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $item['body'] }}</p>
                </article>
            @endforeach
        </div>
    </x-home-section>

    <x-list-section id="where" heading="Where your work goes" :items="$page['where']" />

    <x-list-section id="kept" heading="How long it’s kept" :items="$page['kept']" />

    <x-home-section id="open-source">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Check it yourself</h2>
        <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
            ReviseMy is open source. Everything on this page is in the code on <a href="https://github.com/heyderekj/revisemy" class="link" target="_blank" rel="noreferrer">GitHub</a>, and you can run it yourself to keep every review on your own servers.
        </p>
    </x-home-section>

    <x-home-section id="report">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Found a security problem?</h2>
        <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-zinc-600">
            Please <a href="{{ TrustFacts::reportUrl() }}" class="link" target="_blank" rel="noreferrer">report it privately on GitHub</a> rather than in a public issue, so it can be fixed before anyone else hears about it. Tools can find the same address in <a href="/.well-known/security.txt" class="link">security.txt</a>.
        </p>
    </x-home-section>

    <x-home-section id="faq">
        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">FAQ</h2>
        <div class="mt-6 space-y-6">
            @foreach ($page['faq'] as $item)
                <article>
                    <h3 class="text-sm font-semibold text-zinc-900">{{ $item['q'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-500">{{ $item['a'] }}</p>
                </article>
            @endforeach
        </div>
        <p class="mt-8 text-sm text-zinc-500">The fuller wording is in the <a href="/privacy" class="link">privacy policy</a> and <a href="/terms" class="link">terms</a>.</p>
    </x-home-section>

    <x-link-list heading="Keep going" :links="\App\Support\MarketingPages::more('/security')" />
</x-layouts.marketing>
