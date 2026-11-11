<div class="flex flex-wrap gap-1.5" aria-label="Referenced elements" x-show="{{ $references }}.length">
    <template x-for="(reference, index) in {{ $references }}" :key="reference.key ?? index">
        @if ($removable ?? false)
            <button type="button" class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border bg-chip px-2 py-1 text-xs text-foreground hover:bg-chip-hover"
                :title="reference.label"
                :aria-label="'Remove reference to ' + reference.label"
                @click="{{ $references }} = toggleElementReference({{ $references }}, reference)">
        @else
            <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border bg-chip px-2 py-1 text-xs text-foreground"
                :title="reference.property ? reference.property.label + ' · ' + reference.property.value : reference.summary">
        @endif
                <span class="flex shrink-0 items-center text-muted-foreground" aria-hidden="true">
                    <span x-show="/^#[0-9a-f]{6,8}$/i.test(reference.property?.swatch || '')"
                        class="size-3.5 rounded-full bg-(--rm-swatch) ring-1 ring-inset ring-black/20"
                        :style="/^#[0-9a-f]{6,8}$/i.test(reference.property?.swatch || '') ? '--rm-swatch: ' + reference.property.swatch : ''"></span>
                    <template x-if="['Type', 'Text', 'Font family', 'Font size', 'Font weight', 'Line height', 'Letter spacing'].includes(reference.property?.label)"><flux:icon.language variant="micro" class="size-3.5" /></template>
                    <template x-if="['Size', 'Padding', 'Gap', 'Margin'].includes(reference.property?.label)"><flux:icon.arrows-pointing-out variant="micro" class="size-3.5" /></template>
                    <template x-if="['Corners', 'Border', 'Shadow'].includes(reference.property?.label) && !reference.property?.swatch"><flux:icon.stop variant="micro" class="size-3.5" /></template>
                    <template x-if="['Layout', 'Direction', 'Align items', 'Justify content', 'Wrap'].includes(reference.property?.label)"><flux:icon.squares-2x2 variant="micro" class="size-3.5" /></template>
                    <template x-if="!reference.property"><flux:icon.link variant="micro" class="size-3.5" /></template>
                </span>
                <span x-show="reference.property" class="shrink-0 font-medium" x-text="reference.property?.label"></span>
                <span class="min-w-0 truncate" :class="reference.property ? 'font-mono text-muted-foreground' : ''"
                    x-text="reference.property?.value ?? reference.summary"></span>
        @if ($removable ?? false)
                <span class="shrink-0 text-muted-foreground" aria-hidden="true">×</span>
            </button>
        @else
            </span>
        @endif
    </template>
</div>
