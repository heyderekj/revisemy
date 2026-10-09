{{-- The site navigation, once: the sidebar on desktop and the drawer on a
     phone both draw this. On the homepage the first group jumps within the
     page; elsewhere it links back to those sections. In the drawer, a tap also
     closes it. --}}
@props(['drawer' => false])

@php
    // The page's own path, also during a Livewire update on it (e.g. /reviews).
    $path = trim(\Livewire\Livewire::originalPath(), '/');
    $home = $path === '';
    $base = $home ? '' : '/';

    $groups = [
        $home ? 'On this page' : 'Overview' => [
            ...($home ? [] : [['/', 'Home']]),
            [$base.'#how', 'How it works'],
            [$base.'#agents', 'For agents'],
            [$base.'#setup', 'Connect'],
            [$base.'#pricing', config('billing.pricing_enabled') ? 'Pricing' : 'Credits'],
            [$base.'#faq', 'Questions'],
        ],
        'More' => [
            ['/connectors', 'Connectors'],
            ['/for', 'Use cases'],
            ['/guest-links', 'Guest links'],
            ['/reviews', 'Your reviews'],
            ['/alternatives', 'Alternatives'],
            ['/docs', 'Developer docs'],
            ['https://github.com/heyderekj/revisemy', 'GitHub ↗'],
        ],
    ];

    // The page you're on, or the section it belongs to (/for/websites → Use cases).
    $current = fn (string $href) => str_starts_with($href, '/') && $href !== '/' && ! str_contains($href, '#')
        && ($path === ltrim($href, '/') || str_starts_with($path, ltrim($href, '/').'/'));
@endphp

<nav {{ $attributes->class('flex flex-col gap-8 text-[14px]') }} aria-label="Site">
    @foreach ($groups as $label => $links)
        <div>
            <p class="mb-3 text-sm font-medium text-muted-foreground">{{ $label }}</p>
            <ul class="space-y-2.5 text-zinc-600">
                @foreach ($links as [$href, $text])
                    <li>
                        <a
                            href="{{ $href }}"
                            @class([
                                'block py-0.5 transition-colors hover:text-zinc-900',
                                'font-medium text-zinc-900' => $current($href),
                            ])
                            @if ($current($href)) aria-current="page" @endif
                            @if (str_starts_with($href, 'http')) target="_blank" rel="noreferrer" @endif
                            @if ($drawer) x-on:click="mobileNav = false" @endif
                        >{{ $text }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
