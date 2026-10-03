{{-- Light, dark, or whatever the system says. Flux keeps the choice in
     localStorage and applies it before first paint (@fluxAppearance). --}}
<div {{ $attributes->class('inline-flex items-center gap-0.5 rounded-full bg-trough p-0.5') }} x-data role="radiogroup" aria-label="Appearance">
    @foreach (['light' => ['sun', 'Light'], 'dark' => ['moon', 'Dark'], 'system' => ['computer-desktop', 'Match system']] as $value => [$icon, $label])
        <button
            type="button"
            role="radio"
            class="inline-flex size-7 items-center justify-center rounded-full text-zinc-500 transition-colors hover:text-zinc-900 focus-ring"
            x-bind:class="$flux.appearance === '{{ $value }}' && '!bg-raised !text-zinc-900 shadow-xs'"
            x-bind:aria-checked="($flux.appearance === '{{ $value }}').toString()"
            x-on:click="$flux.appearance = '{{ $value }}'"
            aria-label="{{ $label }}"
            title="{{ $label }}"
        >
            <flux:icon :name="$icon" variant="micro" class="size-4" />
        </button>
    @endforeach
</div>
