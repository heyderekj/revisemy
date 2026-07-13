@props([
    'mcpUrl',
    'token',
    'tokenExpiresAt' => null,
])

@php
    $expires = filled($tokenExpiresAt) ? \Illuminate\Support\Carbon::parse($tokenExpiresAt) : null;
@endphp

<div {{ $attributes->class('overflow-hidden rounded-xl border border-zinc-200 bg-white') }}>
    <div class="grid gap-px bg-zinc-200 sm:grid-cols-2">
        <div class="bg-white p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">MCP URL</p>
                <button
                    type="button"
                    class="text-xs text-rose-600 hover:text-rose-500"
                    x-data
                    x-on:click="navigator.clipboard.writeText($refs.mcpUrl.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                >Copy</button>
            </div>
            <p x-ref="mcpUrl" class="break-all font-mono text-sm text-zinc-700">{{ $mcpUrl }}</p>
        </div>
        <div class="bg-white p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400">Bearer token</p>
                <button
                    type="button"
                    class="text-xs text-rose-600 hover:text-rose-500"
                    x-data
                    x-on:click="navigator.clipboard.writeText($refs.bearerToken.textContent); $el.textContent='Copied'; setTimeout(() => $el.textContent='Copy', 1600)"
                >Copy</button>
            </div>
            <p x-ref="bearerToken" class="break-all font-mono text-sm text-zinc-700">{{ $token }}</p>
        </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/90 px-4 py-3">
        <div class="min-w-0 space-y-0.5">
            @if ($expires)
                <p class="text-[13px] leading-snug text-zinc-600">
                    @if ($expires->isPast())
                        Expired {{ $expires->diffForHumans() }} — generate a new token.
                    @else
                        Expires {{ $expires->diffForHumans() }}
                        <span class="text-zinc-400">· {{ $expires->timezone(config('app.timezone'))->toFormattedDateString() }}</span>
                    @endif
                </p>
            @endif
            <p class="text-[13px] leading-snug text-zinc-500">
                Expired or shared by mistake? Mint a fresh try token.
            </p>
        </div>
        <button
            type="button"
            class="group inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50"
            wire:click="getTryToken"
            wire:loading.attr="disabled"
            onclick="if(window.fathom)fathom.trackEvent('Generate new try token')"
        >
            <flux:icon.arrow-path
                variant="micro"
                class="size-3.5 text-zinc-500 transition group-hover:text-zinc-700"
                wire:loading.class="animate-spin"
                wire:target="getTryToken"
            />
            <span wire:loading.remove wire:target="getTryToken">Generate new token</span>
            <span wire:loading wire:target="getTryToken">Generating…</span>
        </button>
    </div>
</div>
