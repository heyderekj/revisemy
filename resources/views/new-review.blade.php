@php
    $tabs = [
        'link' => ['label' => 'Page', 'icon' => 'globe-alt', 'pending' => 'Capturing the page…'],
        'images' => ['label' => 'Screenshots', 'icon' => 'photo', 'pending' => 'Saving the screenshots…'],
        'pdf' => ['label' => 'Slides', 'icon' => 'presentation-chart-bar', 'pending' => 'Rendering the slides…'],
        'html' => ['label' => 'Email', 'icon' => 'envelope', 'pending' => 'Rendering the email…'],
    ];
    $grant = (int) config('billing.plans.free.credits', 20);
    $field = 'w-full rounded-xl bg-well px-3 py-2.5 text-sm text-foreground outline-none ring-1 ring-transparent placeholder:text-zinc-400 focus:ring-zinc-400';
    $file = 'block w-full rounded-xl bg-well px-3 py-6 text-sm text-zinc-600 ring-1 ring-transparent file:mr-3 file:rounded-full file:border-0 file:bg-card file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground focus-within:ring-zinc-400';
@endphp

<x-layouts.app
    title="Start a review — ReviseMy"
    description="Paste a link or add screenshots, a PDF or an email. Mark it up on a board, then hand the link to your agent. No account."
    :keywords="['design review', 'website feedback', 'mark up a screenshot', 'review a PDF', 'email review', 'AI agent']"
    schema="page"
>
    <x-site-shell>
        <x-home-section first>
            <div class="hidden h-8 lg:block" aria-hidden="true"></div>

            <div class="rm-fade-up max-w-xl lg:mt-6">
                <h1 class="text-[clamp(2rem,5vw,2.75rem)] font-semibold leading-[1.08] tracking-tight text-zinc-900">Start a review</h1>
                <p class="mt-5 text-[15px] leading-relaxed text-pretty text-zinc-600 sm:text-base">
                    Paste a link or add a file. You get a board to mark up now, and its link hands the work to your agent when you’re ready. No account.
                </p>

                <form
                    method="POST"
                    action="{{ route('reviews.submit') }}"
                    enctype="multipart/form-data"
                    class="mt-8 space-y-5"
                    x-data="{ kind: @js($kind), busy: false, pending: @js(collect($tabs)->map(fn ($t) => $t['pending'])) }"
                    x-on:submit="busy = true"
                >
                    @csrf
                    <input type="hidden" name="kind" x-bind:value="kind">

                    <div role="tablist" aria-label="What to review" class="grid grid-cols-2 gap-1 rounded-xl bg-well p-1 sm:grid-cols-4">
                        @foreach ($tabs as $key => $tab)
                            <button
                                type="button"
                                role="tab"
                                x-on:click="kind = @js($key)"
                                x-bind:aria-selected="(kind === @js($key)).toString()"
                                x-bind:class="kind === @js($key) ? 'bg-card text-foreground shadow-sm' : 'text-zinc-500 hover:text-foreground'"
                                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg text-sm font-medium transition-colors"
                            >
                                <flux:icon :name="$tab['icon']" variant="micro" class="size-4" />
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <div x-show="kind === 'link'" @if ($kind !== 'link') x-cloak @endif>
                        <label for="page_url" class="text-sm font-medium text-zinc-700">Page address</label>
                        <input
                            id="page_url"
                            name="page_url"
                            type="text"
                            inputmode="url"
                            autocomplete="url"
                            spellcheck="false"
                            value="{{ old('page_url', $pageUrl) }}"
                            placeholder="example.com/pricing"
                            x-bind:required="kind === 'link'"
                            class="mt-2 {{ $field }}"
                        >
                        <p class="mt-2 text-xs text-muted-foreground">A public page. It’s captured at desktop, tablet and phone sizes.</p>
                        @error('page_url')
                            <p class="mt-2 text-sm text-problem-ink" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="kind === 'images'" @if ($kind !== 'images') x-cloak @endif>
                        <label for="images" class="text-sm font-medium text-zinc-700">Screenshots</label>
                        <input id="images" name="images[]" type="file" multiple accept="image/png,image/jpeg,image/webp" x-bind:required="kind === 'images'" class="mt-2 {{ $file }}">
                        <p class="mt-2 text-xs text-muted-foreground">Up to 5 PNG, JPG or WebP files, 8MB each.</p>
                        @error('images')
                            <p class="mt-2 text-sm text-problem-ink" role="alert">{{ $message }}</p>
                        @enderror
                        @error('images.*')
                            <p class="mt-2 text-sm text-problem-ink" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="kind === 'pdf'" @if ($kind !== 'pdf') x-cloak @endif>
                        <label for="pdf" class="text-sm font-medium text-zinc-700">Slides as a PDF</label>
                        <input id="pdf" name="pdf" type="file" accept="application/pdf" x-bind:required="kind === 'pdf'" class="mt-2 {{ $file }}">
                        <p class="mt-2 text-xs text-muted-foreground">The first 5 pages become the board, up to 20MB.</p>
                        @error('pdf')
                            <p class="mt-2 text-sm text-problem-ink" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="kind === 'html'" @if ($kind !== 'html') x-cloak @endif>
                        <label for="html" class="text-sm font-medium text-zinc-700">Email HTML</label>
                        <input id="html" name="html" type="file" accept=".html,.htm,text/html" x-bind:required="kind === 'html'" class="mt-2 {{ $file }}">
                        <p class="mt-2 text-xs text-muted-foreground">The .html file your email tool exports, rendered 600px wide.</p>
                        @error('html')
                            <p class="mt-2 text-sm text-problem-ink" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="context" class="text-sm font-medium text-zinc-700">What should people look at? <span class="font-normal text-muted-foreground">Optional</span></label>
                        <textarea id="context" name="context" rows="3" maxlength="5000" class="mt-2 {{ $field }}" placeholder="The new pricing table, and whether the CTA reads on mobile.">{{ old('context') }}</textarea>
                        @error('context')
                            <p class="mt-2 text-sm text-problem-ink" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <button
                            type="submit"
                            class="btn-lit inline-flex h-11 items-center justify-center gap-2 rounded-full px-6 text-sm font-medium disabled:opacity-70"
                            x-bind:disabled="busy"
                        >
                            <span x-show="! busy">Start the review</span>
                            <span x-show="busy" x-cloak x-text="pending[kind]"></span>
                        </button>
                        <p class="text-xs leading-relaxed text-muted-foreground sm:max-w-[16rem] sm:text-right">
                            @if ($credits !== null)
                                This browser’s try workspace has {{ $credits }} {{ \Illuminate\Support\Str::plural('credit', $credits) }}.
                            @else
                                Your first review makes a try workspace for this browser, with {{ $grant }} credits a month.
                            @endif
                            A page is {{ $costs['capture_url'] }}, screenshots or slides {{ $costs['images'] }}, an email {{ $costs['html'] }}.
                        </p>
                    </div>
                </form>
            </div>
        </x-home-section>

        <x-home-section>
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">Then hand it to your agent</h2>
            <ol class="mt-6 grid max-w-3xl grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ([
                    ['Mark what matters', 'Draw on the board: must fix, nice to have, a question, or keep this.'],
                    ['Paste the review link', 'Give your agent the link from the board. It picks the review up as its own.'],
                    ['It works through your marks', 'Your agent fixes each one and sends the next pass for you to check.'],
                ] as $i => [$heading, $line])
                    <li class="rounded-xl bg-card px-4 py-4">
                        <span class="flex size-6 items-center justify-center rounded-full bg-zinc-100 text-[11px] font-semibold tabular-nums text-zinc-600" aria-hidden="true">{{ $i + 1 }}</span>
                        <p class="mt-3 text-sm font-medium text-zinc-900">{{ $heading }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-zinc-500">{{ $line }}</p>
                    </li>
                @endforeach
            </ol>
            <p class="mt-6 text-sm text-muted-foreground">No agent connected yet? <a href="/connectors" class="link">Connect the one you use</a>.</p>
        </x-home-section>
    </x-site-shell>
</x-layouts.app>
