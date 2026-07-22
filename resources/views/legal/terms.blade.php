<x-layouts.app
    title="Terms — ReviseMy"
    description="Terms of use for ReviseMy — O'Saasy-licensed software, try tokens, review links, and no warranty. Product-truth draft — not formal legal counsel."
    :keywords="['ReviseMy terms', 'design review terms of use']"
    schema="page"
>
    <x-page-frame>
        <x-home-section first>
            <header>
                <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                    <x-revisemy-logo variant="wordmark" size="lg" />
                </a>
            </header>

            <article class="mt-10 sm:mt-12">
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Legal</p>
                <h1 class="mt-3 text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">
                    Terms
                </h1>
                <p class="mt-4 text-sm text-zinc-500">
                    Last updated July 14, 2026. This is a product-truth draft for an open-source tool — not lawyer-reviewed counsel.
                </p>
            </article>
        </x-home-section>

        <x-home-section>
            <div class="space-y-8 text-[15px] leading-relaxed text-zinc-600">
                <section>
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Software license</h2>
                    <p class="mt-3">
                        The ReviseMy source code is released under the
                        <a href="https://osaasy.dev/" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700" target="_blank" rel="noreferrer">O’Saasy License</a>
                        (<a href="https://github.com/heyderekj/revisemy/blob/main/LICENSE" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700" target="_blank" rel="noreferrer">LICENSE in the repo</a>).
                        That license governs the code — including the reservation of hosted SaaS rights. These terms describe how the hosted demo / try-token experience is intended to be used.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Try tokens, credits, and reviews</h2>
                    <p class="mt-3">
                            Homepage try tokens create a Try workspace so agents can call MCP and REST. Try is a one-time credit pack (no monthly refill). Plus adds a larger monthly grant via Paddle Checkout. Try and Plus use the same full capture quality. Creating a review spends credits by source (images/PDF, email HTML, or URL capture). Rate limits, retention, and expiry may apply (including guest-link and review lifetimes described in product docs). Unused Plus credits do not roll over. Paddle is the merchant of record for paid plans.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Your content</h2>
                    <p class="mt-3">
                        You are responsible for the screenshots, URLs, HTML, PDFs, and notes you submit. Do not upload content you do not have rights to share. Do not use ReviseMy to probe systems you are not allowed to access (for example capture URLs you should not fetch).
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Acceptable use</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>Do not abuse try tokens, spam webhooks, or attempt to disrupt the service.</li>
                        <li>Do not treat secret review or guest tokens as public marketing links.</li>
                        <li>Do not use the product to violate applicable law.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">No warranty</h2>
                    <p class="mt-3">
                        ReviseMy is provided “as is,” without warranty of any kind. Second-opinion hints are suggestions only — human marks and decisions are authoritative. Design outcomes remain your responsibility.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Self-hosted instances</h2>
                    <p class="mt-3">
                        If you deploy your own instance, you set the terms for your users. The O’Saasy license still applies to the software; these hosted-demo notes do not automatically bind your deployment.
                    </p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900">Changes</h2>
                    <p class="mt-3">
                        We may update this draft as the product evolves. The “Last updated” date at the top will change when we do.
                    </p>
                </section>
            </div>

            <p class="mt-12 text-sm text-zinc-500">
                Also see
                <a href="/privacy" class="font-medium text-rose-600 underline decoration-rose-600/30 underline-offset-2 transition hover:text-rose-700">Privacy</a>.
            </p>
        </x-home-section>
    </x-page-frame>
</x-layouts.app>
