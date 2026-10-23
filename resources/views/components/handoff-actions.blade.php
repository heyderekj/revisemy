{{-- Ways to get a site-made review to an agent: open one with the prompt
     filled in (config/hosts.php `open`), or copy it. Opening copies too, so
     a chat that ignores the prefill still takes a paste. After either, the
     page checks every few seconds until an agent opens the link
     (ReviewService::findOrAdopt), so the panel turns over without a reload. --}}
@props(['review'])

@php
    $prompt = 'Open my ReviseMy review '.$review->reviewUrl().' and work through my marks.';
    $hosts = collect(config('hosts.connect'))
        ->filter(fn (array $host) => isset($host['open']))
        ->map(fn (array $host) => ['name' => $host['name'], 'icon' => $host['icon'] ?? null, 'url' => str_replace('{prompt}', rawurlencode($prompt), $host['open'])]);
    $connected = $review->workspace?->assistantNames() ?? [];
    // The one this browser already connected goes first; Claude otherwise.
    $first = $hosts->first(fn (array $host) => in_array($host['name'], $connected, true)) ?? $hosts->first();
    $others = $hosts->reject(fn (array $host) => $host['name'] === $first['name'])->values();
@endphp

<div
    x-data="{
        prompt: @js($prompt),
        copied: false,
        async copy() {
            try { await navigator.clipboard.writeText(this.prompt); } catch (e) {}
            this.copied = true;
            setTimeout(() => this.copied = false, 1800);
            this.watch();
        },
        watch() {
            if (window.rmHandoffWatch) return;
            const wire = $wire;
            const until = Date.now() + 180000;
            window.rmHandoffWatch = setInterval(() => {
                if (Date.now() > until || ! document.querySelector('[data-handoff=waiting]')) {
                    clearInterval(window.rmHandoffWatch);
                    window.rmHandoffWatch = null;
                    return;
                }
                wire.loadReview();
            }, 4000);
        },
    }"
    {{ $attributes }}
>
    <div class="flex flex-wrap items-center gap-2">
        @if ($first)
            <a href="{{ $first['url'] }}" target="_blank" rel="noopener" x-on:click="copy()" class="btn-lit inline-flex h-9 items-center justify-center gap-1.5 rounded-full px-4 text-sm font-medium">
                Open in {{ $first['name'] }}
                <flux:icon.arrow-up-right variant="micro" class="size-3.5 opacity-70" />
            </a>
        @endif
        <button type="button" x-on:click="copy()" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-full bg-chip px-3.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-chip-hover">
            <flux:icon.clipboard-document variant="micro" class="size-4" x-show="! copied" />
            <flux:icon.check variant="micro" class="size-4 text-done-ink" x-show="copied" x-cloak />
            <span x-text="copied ? 'Copied' : 'Copy prompt'">Copy prompt</span>
        </button>
    </div>

    @if ($others->isNotEmpty())
        <p class="mt-2.5 text-xs text-muted-foreground">
            Or open in
            @foreach ($others as $i => $host)
                <a href="{{ $host['url'] }}" target="_blank" rel="noopener" x-on:click="copy()" class="link">{{ $host['name'] }}</a>{{ $i < $others->count() - 2 ? ',' : ($i === $others->count() - 2 ? ' or' : '.') }}
            @endforeach
            Any other agent takes a paste.
        </p>
    @endif

    @if ($connected === [])
        <p class="mt-2 text-xs text-muted-foreground">Your agent needs ReviseMy connected first. <a href="/connectors" class="link">Connect the one you use</a>, it takes a minute.</p>
    @endif

    <p class="mt-3 select-all break-words rounded-xl bg-well px-3 py-2 text-xs leading-relaxed text-zinc-600">{{ $prompt }}</p>
</div>
