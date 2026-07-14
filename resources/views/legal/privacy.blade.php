<x-layouts.app
    title="Privacy — ReviseMy"
    description="How ReviseMy handles try tokens, review captures, guest links, and analytics. Product-truth draft — not formal legal counsel."
    :keywords="['ReviseMy privacy', 'design review privacy', 'try token']"
    schema="page"
>
    <div class="rm-wash relative min-h-screen">
        <div class="rm-grid pointer-events-none absolute inset-0"></div>

        <div class="relative z-10 mx-auto max-w-[720px] px-5 pb-20 pt-8 sm:px-8 sm:pb-24 sm:pt-10">
            <header class="border-b border-zinc-900/8 pb-6">
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo class="!h-auto !w-[128px]" />
                </a>
            </header>

            <article class="mt-10 sm:mt-12">
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Legal</p>
                <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                    Privacy
                </h1>
                <p class="mt-4 text-sm text-zinc-500">
                    Last updated July 14, 2026. This is a product-truth draft for an open-source tool — not lawyer-reviewed counsel.
                </p>

                <div class="mt-10 space-y-8 text-[15px] leading-relaxed text-zinc-600">
                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900">What ReviseMy is</h2>
                        <p class="mt-3">
                            ReviseMy is an open-source human-in-the-loop design checkup for AI coding agents. You (or your agent) create reviews with screenshots, live URL captures, PDF slides, or email HTML. Humans mark regions and decide; agents read structured next steps over MCP or REST.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900">What we store</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5">
                            <li><span class="font-medium text-zinc-800">Try workspaces</span> — a Sanctum personal access token from the homepage so agents can call MCP/API without a traditional signup.</li>
                            <li><span class="font-medium text-zinc-800">Reviews</span> — titles, status, marks, comments, guest suggestions, second-opinion findings, and related metadata.</li>
                            <li><span class="font-medium text-zinc-800">Captures</span> — screenshot (and optional thumbnail) files on the configured disk (local or object storage).</li>
                            <li><span class="font-medium text-zinc-800">Guest share settings</span> — share tokens and optional expiry for guest links.</li>
                            <li><span class="font-medium text-zinc-800">Optional webhook URL</span> — if you pass <code class="font-mono text-[13px] text-rose-600">webhook_url</code> on create, we store it to deliver <code class="font-mono text-[13px] text-rose-600">review.decided</code> events.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Access model</h2>
                        <p class="mt-3">
                            Review links are secret URLs. Anyone with the owner token can mark and decide. Guest links are separate and limited to suggestions. Treat tokens like passwords — do not post them publicly.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Third parties</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5">
                            <li><span class="font-medium text-zinc-800">Hosting &amp; storage</span> — when you use a hosted deploy (for example Laravel Cloud), infrastructure providers process the data you put in reviews under their terms.</li>
                            <li><span class="font-medium text-zinc-800">Optional vision</span> — if the operator sets Anthropic or OpenAI (or compatible) keys, screenshot content may be sent to that provider for second-opinion hints.</li>
                            <li><span class="font-medium text-zinc-800">Optional capture</span> — URL/email/PDF capture may call a Browserless-compatible endpoint configured by the operator.</li>
                            <li><span class="font-medium text-zinc-800">Analytics</span> — the public marketing site may use privacy-oriented analytics (for example Fathom) for aggregate traffic — not for reading your review marks.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Self-hosting</h2>
                        <p class="mt-3">
                            If you run ReviseMy yourself, you control retention, storage region, API keys, and who can reach your origin. This page describes the default product behavior; your deployment policy may be stricter.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900">What we do not do</h2>
                        <p class="mt-3">
                            We do not sell review contents. We do not use your captures to train a ReviseMy foundation model. Second-opinion providers (when enabled) are chosen by the operator via their own API keys.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Contact</h2>
                        <p class="mt-3">
                            Questions about this draft: open an issue on
                            <a href="https://github.com/heyderekj/revisemy" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700" target="_blank" rel="noreferrer">GitHub</a>
                            or reach Derek via the contact paths on the homepage.
                        </p>
                    </section>
                </div>

                <p class="mt-12 text-sm text-zinc-500">
                    Also see
                    <a href="/terms" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Terms</a>.
                </p>
            </article>
        </div>
    </div>
</x-layouts.app>
