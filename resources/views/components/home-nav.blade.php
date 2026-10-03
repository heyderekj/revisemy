{{-- The homepage's navigation, once: the sidebar on desktop and the drawer on
     a phone both draw this. In the drawer, a tap also closes it. --}}
@props(['drawer' => false])

@php
    $groups = [
        'On this page' => [
            ['#how', 'How it works'],
            ['#agents', 'For agents'],
            ['#setup', 'Connect'],
            ['#pricing', config('billing.pricing_enabled') ? 'Pricing' : 'Credits'],
            ['#faq', 'Questions'],
        ],
        'More' => [
            ['/connectors', 'Connectors'],
            ['/guest-links', 'Guest links'],
            ['/reviews', 'Your reviews'],
            ['/alternatives', 'Alternatives'],
            ['https://github.com/heyderekj/revisemy', 'GitHub ↗'],
        ],
    ];
@endphp

<nav {{ $attributes->class('flex flex-col gap-8 text-[14px]') }}>
    @foreach ($groups as $label => $links)
        <div>
            <p class="mb-3 text-sm font-medium text-muted-foreground">{{ $label }}</p>
            <ul class="space-y-2.5 text-zinc-600">
                @foreach ($links as [$href, $text])
                    <li>
                        <a
                            href="{{ $href }}"
                            class="block py-0.5 transition-colors hover:text-zinc-900"
                            @if (str_starts_with($href, 'http')) target="_blank" rel="noreferrer" @endif
                            @if ($drawer) x-on:click="mobileNav = false" @endif
                        >{{ $text }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
