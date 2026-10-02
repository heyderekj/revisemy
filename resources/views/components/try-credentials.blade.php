@props([
    'mcpUrl',
    'token',
    'tokenExpiresAt' => null,
])

@php
    $expires = filled($tokenExpiresAt) ? \Illuminate\Support\Carbon::parse($tokenExpiresAt) : null;
@endphp

<div {{ $attributes->class('overflow-hidden rounded-2xl bg-card') }}>
    <div class="grid gap-2 p-2 sm:grid-cols-2">
        <div class="rounded-xl bg-raised p-4">
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
        <div class="rounded-xl bg-raised p-4">
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
            @if ($expires)
                <p class="mt-2 text-[12px] leading-snug text-zinc-500">
                    @if ($expires->isPast())
                        Expired {{ $expires->diffForHumans() }}
                    @else
                        Expires {{ $expires->diffForHumans() }}
                        <span class="text-zinc-400">· {{ $expires->timezone(config('app.timezone'))->toFormattedDateString() }}</span>
                    @endif
                </p>
            @endif
        </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 pt-2">
        <p class="text-[13px] text-zinc-600">Or install it in one click, with this token:</p>
        <x-install-links :token="$token" />
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <p class="min-w-0 text-[13px] leading-snug text-zinc-500">
            @if ($expires?->isPast())
                This try token has expired — generate a new one.
            @elseif ($expires)
                        @if (config('billing.plans.free.renews'))
                            Try includes {{ (int) config('billing.plans.free.credits', 20) }} credits each month (full quality, no rollover). Tokens last {{ (int) config('billing.plans.free.token_days', \App\Services\TryTokenService::TOKEN_DAYS) }} days. Shared by mistake? Mint a fresh one (limited per day).
                        @else
                            Try includes {{ (int) config('billing.plans.free.credits', 20) }} credits once (full quality, no monthly refill). Tokens last {{ (int) config('billing.plans.free.token_days', \App\Services\TryTokenService::TOKEN_DAYS) }} days. Shared by mistake? Mint a fresh one (limited per day).
                        @endif
            @else
                No expiry on this token — generate a new one for a {{ \App\Services\TryTokenService::TOKEN_DAYS }}-day lifetime.
            @endif
        </p>
        <button
            type="button"
            class="group btn-quiet inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium"
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
