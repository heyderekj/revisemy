@php
    $skills = [
        'homepage' => [
            'name' => 'Homepage skills',
            'icon' => 'home',
            'href' => '/skills/homepage',
            'title' => 'Lead with the subscription',
            'note' => 'Make “Fresh beans every two weeks” the headline.',
            'area' => 'left-[7.5%] top-[24%] h-[17.5%] w-[49%]',
        ],
        'better-writing' => [
            'name' => 'Better Writing',
            'icon' => 'pencil-square',
            'href' => '/skills#skill-better-writing',
            'title' => 'Say what the button opens',
            'note' => 'Change “Start a subscription” to “Choose a coffee plan”.',
            'area' => 'left-[7.5%] top-[47.5%] h-[13%] w-[18%]',
        ],
        'better-typography' => [
            'name' => 'Better Typography',
            'icon' => 'language',
            'href' => '/skills#skill-better-typography',
            'title' => 'Make the supporting line easier to read',
            'note' => 'Increase the type below the headline and leave more space above the button.',
            'area' => 'left-[7.5%] top-[40.5%] h-[8.5%] w-[49%]',
        ],
        'review-animations' => [
            'name' => 'Review Animations',
            'icon' => 'bolt',
            'href' => '/skills#skill-review-animations',
            'title' => 'Shorten the menu exit',
            'note' => 'The menu takes 420 ms to close. Try 180 ms so people can get back to the page sooner.',
        ],
        'prototype' => [
            'name' => 'Prototype',
            'icon' => 'rectangle-group',
            'href' => '/skills#skill-prototype',
            'title' => 'Try both starting points',
            'note' => 'See which layout helps a first-time shopper find a subscription faster.',
        ],
        'checkup' => [
            'name' => 'Design checkup',
            'icon' => 'arrow-path',
            'href' => '/connectors#claude-code-plugin',
            'title' => 'Check the next pass',
            'note' => 'The new headline leads with the subscription. Verify it on the updated capture.',
        ],
    ];
@endphp

<x-home-section id="review-skills" joined class="@container/review-skills !scroll-mt-20 !pb-8 lg:!scroll-mt-8" aria-labelledby="rm-review-skills">
    <h2 id="rm-review-skills" class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">See what to fix before you ship</h2>
    <p class="mt-2 text-[15px] leading-relaxed text-pretty text-zinc-600">
        Start with <a href="/second-opinion" class="link">free website checks</a>. Add focused feedback with MCP skills.
    </p>

    <div x-data="{ skill: 'homepage' }" class="mt-5 grid min-w-0 items-start gap-4 @min-[40rem]/review-skills:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] @min-[40rem]/review-skills:gap-6">
        <fieldset class="grid min-w-0 grid-cols-2 gap-2">
            <legend class="sr-only">Choose a skill to preview</legend>
            @foreach ($skills as $id => $entry)
                <div class="relative min-w-0">
                    <input
                        id="rm-skill-{{ $id }}"
                        name="homepage-review-skill"
                        type="radio"
                        value="{{ $id }}"
                        x-model="skill"
                        aria-controls="rm-skill-preview"
                        class="peer sr-only"
                        @checked($loop->first)
                    >
                    <label for="rm-skill-{{ $id }}" class="flex h-full min-h-16 cursor-pointer items-center gap-2 rounded-xl border border-border bg-card p-2.5 text-[13px] font-medium leading-tight text-zinc-800 transition-colors hover:bg-chip-hover peer-checked:border-key peer-checked:bg-key/15 peer-checked:text-zinc-900 peer-checked:hover:bg-key/20 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent-content @min-[40rem]/review-skills:min-h-24 @min-[40rem]/review-skills:flex-col @min-[40rem]/review-skills:items-start @min-[40rem]/review-skills:justify-center @min-[40rem]/review-skills:gap-2.5 @min-[40rem]/review-skills:px-3.5 @min-[40rem]/review-skills:text-sm">
                        <span class="grid size-7 shrink-0 place-items-center rounded-md bg-raised text-zinc-700 ring-1 ring-border" aria-hidden="true">
                            <flux:icon :name="$entry['icon']" variant="micro" class="size-4" />
                        </span>
                        {{ $entry['name'] }}
                    </label>
                </div>
            @endforeach
        </fieldset>

        <figure id="rm-skill-preview" class="rm-dot-grid grid min-w-0 gap-2 rounded-2xl bg-card p-2.5 ring-1 ring-border sm:p-3">
            <div class="min-w-0 overflow-hidden rounded-lg bg-raised shadow-sm ring-1 ring-border" aria-hidden="true">
                <div class="flex h-8 items-center gap-1.5 border-b border-border px-2.5 text-xs font-medium text-zinc-600">
                    <flux:icon.link variant="micro" class="size-3.5" />
                    fieldnote.coffee
                    <span x-show="skill === 'checkup'" x-cloak class="ml-auto inline-flex items-center gap-1 text-done-ink">
                        <flux:icon.check variant="micro" class="size-3.5" />
                        Ready to verify
                    </span>
                </div>

                <div class="relative aspect-[16/10]">
                    <div x-show="['homepage', 'better-writing', 'better-typography'].includes(skill)" class="absolute inset-0">
                        @include('components.review-mock.samples.website')
                        @foreach ($skills as $id => $entry)
                            @isset($entry['area'])
                                <div x-show="skill === '{{ $id }}'" @unless($loop->first) x-cloak @endunless class="absolute rounded border-2 border-dashed border-sky-500 bg-sky-500/10 {{ $entry['area'] }}">
                                    <span class="absolute top-0 left-0 flex size-5 -translate-3/4 items-center justify-center rounded-full border border-dashed border-sky-500 bg-raised text-[10px] font-semibold text-sky-700 shadow-sm">S1</span>
                                </div>
                            @endisset
                        @endforeach
                    </div>

                    <div x-show="skill === 'review-animations'" x-cloak class="absolute inset-0 grid grid-cols-2 items-center gap-3 bg-well p-3 sm:gap-5 sm:p-5">
                        <div class="min-w-0 overflow-hidden rounded-lg bg-raised shadow-sm ring-1 ring-border">
                            <div class="flex items-center justify-between gap-1 border-b border-border px-2 py-2 text-[11px] font-semibold text-zinc-900 sm:px-3">
                                Fieldnote
                                <flux:icon.x-mark variant="micro" class="size-3.5 shrink-0 text-zinc-500" />
                            </div>
                            <ul class="divide-y divide-border px-2.5 text-[11px] text-zinc-700 sm:px-3 sm:text-xs">
                                <li class="py-1.5">Shop</li>
                                <li class="py-1.5">Subscribe</li>
                                <li class="py-1.5">Journal</li>
                            </ul>
                        </div>
                        <div class="grid min-w-0 gap-3.5 text-[11px] font-medium text-zinc-700 sm:text-xs">
                            <div class="grid grid-cols-[1fr_auto] gap-x-1 gap-y-1.5">
                                <span>Opens</span>
                                <span class="tabular-nums">180 ms</span>
                                <div class="col-span-2 h-2 rounded-sm bg-raised ring-1 ring-border">
                                    <div class="h-full w-[43%] rounded-sm bg-sky-300"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-[1fr_auto] gap-x-1 gap-y-1.5 text-sky-800">
                                <span>Closes</span>
                                <span class="tabular-nums">420 ms</span>
                                <div class="col-span-2 h-2 rounded-sm border border-dashed border-sky-600 bg-sky-200"></div>
                            </div>
                        </div>
                    </div>

                    <div x-show="skill === 'prototype'" x-cloak class="absolute inset-0 grid grid-cols-2 content-center gap-2.5 bg-well p-3 sm:gap-4 sm:p-5">
                        @foreach ([['Plans first', 'Fresh beans every two weeks', false], ['Coffee first', 'Explore the latest roast', true]] as [$label, $heading, $imageFirst])
                            <div class="min-w-0 overflow-hidden rounded-lg bg-raised shadow-sm ring-1 ring-border">
                                <p class="border-b border-border px-2 py-1.5 text-[11px] font-medium text-zinc-600 sm:px-2.5">{{ $label }}</p>
                                <div class="flex flex-col gap-1.5 p-2 sm:gap-2 sm:p-2.5">
                                    <p class="text-xs font-semibold leading-tight tracking-tight text-zinc-900 sm:text-sm">{{ $heading }}</p>
                                    <img src="{{ asset('images/fieldnote-coffee.webp') }}" alt="" width="768" height="576" loading="lazy" decoding="async" class="aspect-[2.4] w-full rounded-sm object-cover {{ $imageFirst ? 'order-first' : '' }}">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div x-show="skill === 'checkup'" x-cloak class="absolute inset-0">
                        @include('components.review-mock.samples.website', ['websiteHeading' => 'Fresh beans every two weeks.', 'websiteDescription' => 'Roasted the morning they ship.'])
                        <div class="absolute top-[24%] left-[7.5%] h-[17.5%] w-[49%] rounded border-2 border-done bg-done/10"></div>
                    </div>
                </div>
            </div>

            <figcaption class="min-w-0 rounded-lg bg-raised px-3 py-2.5 ring-1 ring-border" aria-live="polite">
                @foreach ($skills as $id => $entry)
                    <div x-show="skill === '{{ $id }}'" @unless($loop->first) x-cloak @endunless class="flex items-start justify-between gap-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold tracking-tight text-zinc-900">{{ $entry['title'] }}</p>
                            <p class="mt-0.5 text-sm leading-snug text-pretty text-zinc-600">{{ $entry['note'] }}</p>
                        </div>
                        <a href="{{ $entry['href'] }}" class="inline-flex min-h-8 shrink-0 items-center gap-1 rounded-md bg-chip px-2 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover hover:text-zinc-900" aria-label="Guide for {{ $entry['name'] }}">
                            Guide <flux:icon.arrow-right variant="micro" class="size-3" />
                        </a>
                    </div>
                @endforeach
            </figcaption>
        </figure>
    </div>
</x-home-section>
