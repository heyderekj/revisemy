@props([
    'fathomEvent' => 'Try token',
])

<div class="pointer-events-none sticky top-8 z-30 hidden h-0 sm:block">
    <div
        class="flex justify-end px-[var(--rm-pad)]"
        x-show="! atCta"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="pointer-events-auto">
            <x-try-token-cta :fathom-event="$fathomEvent" />
        </div>
    </div>
</div>
