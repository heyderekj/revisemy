@props(['references', 'action' => null, 'markId' => null])

@if ($references)
    <div {{ $attributes->class(['flex flex-wrap gap-1.5']) }} aria-label="Referenced elements">
        @foreach ($references as $reference)
            @php
                $label = \App\Support\ElementTarget::referenceLabel($reference);
                $property = $reference['property'] ?? null;
                $swatch = \App\Support\ElementMap::colour($property['swatch'] ?? null);
                $icon = match ($property['label'] ?? null) {
                    'Type', 'Text', 'Font family', 'Font size', 'Font weight', 'Line height', 'Letter spacing' => 'language',
                    'Size', 'Padding', 'Gap', 'Margin' => 'arrows-pointing-out',
                    'Corners', 'Border', 'Shadow' => 'stop',
                    'Layout', 'Direction', 'Align items', 'Justify content', 'Wrap' => 'squares-2x2',
                    default => 'link',
                };
            @endphp
            @if ($action)
                <button type="button" wire:key="element-reference-{{ $action }}-{{ $markId }}-{{ \App\Support\ElementTarget::referenceKey($reference) }}"
                    wire:click="{{ $action }}(@js(\App\Support\ElementTarget::referenceKey($reference)){{ $markId !== null ? ', '.$markId : '' }})"
                    class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border bg-chip px-2 py-1 text-xs text-foreground hover:bg-chip-hover"
                    aria-label="Remove reference to {{ $label }}" title="{{ $label }}">
            @else
                <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border bg-chip px-2 py-1 text-xs text-foreground" title="{{ $label }}">
            @endif
                @if ($swatch)
                    <span class="size-3.5 shrink-0 rounded-full bg-(--rm-swatch) ring-1 ring-inset ring-black/20" style="--rm-swatch: {{ $swatch }}" aria-hidden="true"></span>
                @else
                    <flux:icon :name="$icon" variant="micro" class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                @endif
                @if ($property)
                    <span class="shrink-0 font-medium">{{ $property['label'] }}</span>
                    <span class="min-w-0 truncate font-mono text-muted-foreground">{{ $property['value'] }}</span>
                @else
                    <span class="min-w-0 truncate">{{ $label }}</span>
                @endif
            @if ($action)
                    <flux:icon.x-mark variant="micro" class="size-3 shrink-0 text-muted-foreground" aria-hidden="true" />
                </button>
            @else
                </span>
            @endif
        @endforeach
    </div>
@endif
