@props([
    'taste',
])

@php
    $label = $taste['label'] ?? 'Craft';
    $disclaimer = $taste['disclaimer'] ?? '';
    $lenses = $taste['lenses'] ?? [];
@endphp

{{-- Info trigger; panel is fixed so aside overflow-y-auto cannot clip it. --}}
<div
    class="relative shrink-0"
    x-data="{
        open: false,
        style: '',
        toggle() {
            if (this.open) {
                this.open = false;
                return;
            }
            // Defer open so the triggering click is not treated as click.outside.
            this.$nextTick(() => {
                this.open = true;
                this.$nextTick(() => this.place());
            });
        },
        place() {
            const btn = this.$refs.trigger;
            const panel = this.$refs.panel;
            if (! btn || ! panel) return;
            const r = btn.getBoundingClientRect();
            const gap = 8;
            const width = Math.min(320, window.innerWidth - 16);
            let left = r.right - width;
            left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
            const height = Math.min(panel.scrollHeight || 360, window.innerHeight - 16);
            let top = r.bottom + gap;
            if (top + height > window.innerHeight - 8) {
                top = Math.max(8, r.top - gap - height);
            }
            this.style = `top:${top}px; left:${left}px; width:${width}px; max-height:${window.innerHeight - 16}px`;
        },
    }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        x-ref="trigger"
        class="inline-flex size-6 items-center justify-center rounded-full bg-sky-100 text-sky-800 transition hover:bg-sky-200/80"
        x-on:click.stop="toggle()"
        x-bind:aria-expanded="open.toString()"
        aria-haspopup="dialog"
        aria-label="About second opinion"
        title="About second opinion"
    >
        <flux:icon.information-circle variant="micro" class="size-3.5" />
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-ref="panel"
            x-bind:style="style"
            x-transition:enter="transition ease-out duration-120"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-80"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            class="fixed z-[80] overflow-y-auto rounded-xl border border-sky-200/80 bg-white p-3 shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]"
            role="dialog"
            aria-label="About second opinion"
            @click.outside="open = false"
            @resize.window="open && place()"
            @scroll.window="open && place()"
        >
            <p class="text-xs font-medium text-sky-950">About second opinion</p>
            <p class="mt-1 text-[11px] leading-relaxed text-zinc-500">
                Hints only — never approve or request changes. Your marks stay authoritative. Accept a hint to turn it into one of your marks; dismiss the rest.
            </p>
            <p class="mt-2 text-[11px] leading-relaxed text-zinc-500">
                What’s factoring in for this review (<span class="font-medium text-zinc-700">{{ $label }}</span>): a free type-aware checklist on every upload, optional vision regions when a model key is set, plus these public craft sources:
            </p>
            <ul class="mt-2 space-y-2.5">
                @foreach ($lenses as $lens)
                    <li>
                        <p class="text-xs font-semibold text-zinc-800">{{ $lens['name'] }}</p>
                        <p class="mt-0.5 text-[11px] leading-relaxed text-zinc-500">{{ $lens['blurb'] }}</p>
                        @if (! empty($lens['source_url']))
                            <a
                                href="{{ $lens['source_url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-block text-[11px] font-medium text-sky-700 underline decoration-sky-700/30 underline-offset-2 transition hover:text-sky-900"
                                x-on:click.stop
                            >{{ $lens['source_label'] ?: $lens['source_url'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($disclaimer !== '')
                <p class="mt-3 border-t border-sky-100 pt-2 text-[10px] leading-relaxed text-zinc-400">{{ $disclaimer }}</p>
            @endif
            <p class="mt-2 text-[10px] text-zinc-400">
                <a href="/second-opinion" class="font-medium text-sky-700 underline decoration-sky-700/30 underline-offset-2 hover:text-sky-900" x-on:click.stop>More on /second-opinion</a>
            </p>
        </div>
    </template>
</div>
