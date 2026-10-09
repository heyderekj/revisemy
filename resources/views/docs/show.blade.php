{{-- A developer doc: the page's markdown, with the list of docs and this
     page's sections beside it on a wide screen and folded above it on a
     narrow one. The overview at /docs adds a card for every page. --}}
@php
    $isIndex = $page['slug'] === 'index';
    $isCurrent = fn (array $doc) => $doc['slug'] === $page['slug'];
@endphp

<x-layouts.marketing
    :title="$isIndex ? 'Developer docs · ReviseMy' : $page['title'].' · ReviseMy developer docs'"
    :description="$page['description']"
    :keywords="['ReviseMy API', 'ReviseMy MCP', 'design review API', 'MCP server docs', 'review webhook']"
    fathom="Developer docs"
>
    <x-marketing-hero :eyebrow="$isIndex ? 'Developers' : 'Developer docs'" :icon="$page['icon']" :headline="$page['title']" :subheadline="$page['description']" />

    <x-home-section joined class="!pt-0">
        {{-- Phone and tablet: the docs, folded. --}}
        <details class="group mb-8 rounded-xl bg-card xl:hidden">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-zinc-900 [&::-webkit-details-marker]:hidden">
                <span>Developer docs <span class="font-normal text-zinc-500">· {{ $page['nav'] }}</span></span>
                <flux:icon.chevron-down variant="micro" class="size-4 text-zinc-500 transition-transform group-open:rotate-180" />
            </summary>
            <ul class="space-y-1 px-2 pb-3 text-[14px]">
                @foreach ($pages as $doc)
                    <li>
                        <a
                            href="{{ $doc['path'] }}"
                            @class([
                                'block rounded-lg px-2 py-1.5 transition-colors hover:bg-chip',
                                'font-medium text-zinc-900' => $isCurrent($doc),
                                'text-zinc-600' => ! $isCurrent($doc),
                            ])
                            @if ($isCurrent($doc)) aria-current="page" @endif
                        >{{ $doc['nav'] }}</a>
                    </li>
                @endforeach
            </ul>
        </details>

        <div class="xl:grid xl:grid-cols-[minmax(0,1fr)_11rem] xl:gap-12">
            <article
                class="rm-docs min-w-0"
                x-data
                x-init="$el.querySelectorAll('pre').forEach((pre) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'rm-docs-copy';
                    button.textContent = 'Copy';
                    button.addEventListener('click', () => {
                        navigator.clipboard.writeText(pre.querySelector('code')?.textContent ?? pre.textContent);
                        button.textContent = 'Copied';
                        setTimeout(() => button.textContent = 'Copy', 1600);
                    });
                    pre.append(button);
                })"
            >
                {!! $rendered['html'] !!}
            </article>

            {{-- Wide screens: every doc, then this page's sections. --}}
            <aside class="hidden xl:block" aria-label="Developer docs">
                <div class="sticky top-24 space-y-8 text-[13px]">
                    <nav>
                        <p class="mb-3 font-medium text-muted-foreground">Developer docs</p>
                        <ul class="space-y-2">
                            @foreach ($pages as $doc)
                                <li>
                                    <a
                                        href="{{ $doc['path'] }}"
                                        @class([
                                            'block transition-colors hover:text-zinc-900',
                                            'font-medium text-zinc-900' => $isCurrent($doc),
                                            'text-zinc-600' => ! $isCurrent($doc),
                                        ])
                                        @if ($isCurrent($doc)) aria-current="page" @endif
                                    >{{ $doc['nav'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>

                    @if (count($rendered['headings']) > 1)
                        <nav aria-label="On this page">
                            <p class="mb-3 font-medium text-muted-foreground">On this page</p>
                            <ul class="space-y-2">
                                @foreach ($rendered['headings'] as $heading)
                                    <li><a href="#{{ $heading['id'] }}" class="block text-zinc-600 transition-colors hover:text-zinc-900">{{ $heading['text'] }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif
                </div>
            </aside>
        </div>

        {{-- The way on, and the way to fix this page. --}}
        <div class="mt-12 flex flex-col gap-3 border-t border-dashed border-border-strong pt-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-wrap gap-2">
                @if ($neighbours['previous'])
                    <a href="{{ $neighbours['previous']['path'] }}" class="inline-flex h-8 items-center gap-1.5 rounded-full bg-chip px-3.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-chip-hover">
                        <flux:icon.arrow-left variant="micro" class="size-3.5" />
                        {{ $neighbours['previous']['nav'] }}
                    </a>
                @endif
                @if ($neighbours['next'])
                    <a href="{{ $neighbours['next']['path'] }}" class="inline-flex h-8 items-center gap-1.5 rounded-full bg-chip px-3.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-chip-hover">
                        {{ $neighbours['next']['nav'] }}
                        <flux:icon.arrow-right variant="micro" class="size-3.5" />
                    </a>
                @endif
            </div>
            <p class="font-mono text-xs leading-8 text-muted-foreground">
                <a href="{{ $editUrl }}" target="_blank" rel="noreferrer" class="underline decoration-border-strong underline-offset-2 transition-colors hover:text-zinc-900">Edit this page on GitHub</a>
                · <a href="{{ $page['path'] }}.md" class="underline decoration-border-strong underline-offset-2 transition-colors hover:text-zinc-900">Markdown</a>
            </p>
        </div>
    </x-home-section>

    @if ($isIndex)
        <x-link-list
            heading="Every page"
            :links="collect($pages)->reject($isCurrent)->map(fn (array $doc) => ['href' => $doc['path'], 'label' => $doc['title'], 'line' => $doc['description'], 'icon' => $doc['icon']])->values()->all()"
        />
    @endif
</x-layouts.marketing>
