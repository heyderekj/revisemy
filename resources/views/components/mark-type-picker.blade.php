<label class="relative inline-flex max-w-full items-center">
    <span class="sr-only">Kind of mark</span>
    <select {{ $attributes->class('h-8 max-w-full appearance-none rounded-full bg-chip py-0 ps-3 pe-8 text-base font-medium text-zinc-800 transition-colors hover:bg-chip-hover focus-visible:outline-2 focus-visible:outline-key sm:text-xs') }}>
        @foreach (\App\Models\Annotation::severityLabels() as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    <flux:icon.chevron-down variant="micro" class="pointer-events-none absolute end-2.5 size-3.5 text-muted-foreground" />
</label>
