{{-- Claude Desktop vs Code. Parent must define Alpine `claudePath` ('desktop' | 'code'). --}}
<div {{ $attributes->class('space-y-2') }}>
    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">
        Choose one
    </p>
    <div class="grid gap-2 sm:grid-cols-2" role="tablist" aria-label="Claude product">
        <button
            type="button"
            role="tab"
            class="rounded-xl border px-3.5 py-3 text-left transition"
            :class="claudePath === 'desktop'
                ? 'border-zinc-900 bg-white shadow-sm'
                : 'border-zinc-200 bg-zinc-50/80 hover:border-zinc-300 hover:bg-white'"
            :aria-selected="claudePath === 'desktop'"
            x-on:click="claudePath = 'desktop'"
        >
            <p class="text-sm font-medium text-zinc-900">Claude Desktop</p>
            <p class="mt-0.5 text-[13px] leading-snug text-zinc-500">
                Inline MCP Apps · mark and approve in chat
            </p>
        </button>
        <button
            type="button"
            role="tab"
            class="rounded-xl border px-3.5 py-3 text-left transition"
            :class="claudePath === 'code'
                ? 'border-zinc-900 bg-white shadow-sm'
                : 'border-zinc-200 bg-zinc-50/80 hover:border-zinc-300 hover:bg-white'"
            :aria-selected="claudePath === 'code'"
            x-on:click="claudePath = 'code'"
        >
            <p class="text-sm font-medium text-zinc-900">Claude Code</p>
            <p class="mt-0.5 text-[13px] leading-snug text-zinc-500">
                Terminal CLI · shares a <code class="font-mono text-[12px]">review_url</code>
            </p>
        </button>
    </div>
</div>
