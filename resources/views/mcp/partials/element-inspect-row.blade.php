<div x-data="{
        get reference() { return { key: {{ $source }}?.index + ':' + row.label, label: row.label + ' · ' + row.value, property: row }; },
        get selectable() { return {{ ($requiresComments ?? false) ? 'payload.comments_enabled && ' : '' }}Number.isInteger({{ $source }}?.index); },
        get selected() { return {{ $references }}.some(item => item.key === this.reference.key); },
    }" class="relative flex min-h-8 items-center gap-2 rounded-md px-2 py-1 has-[button:hover]:bg-chip">
    <span class="w-[4.25rem] shrink-0 text-left text-muted-foreground" x-text="row.label"></span>
    <span class="flex min-w-0 flex-1 items-center gap-1.5 break-words font-mono text-zinc-700">
        <span class="size-4 shrink-0 rounded-sm bg-(--rm-swatch) ring-1 ring-black/10" aria-hidden="true"
            x-show="row.swatch && /^#[0-9a-f]{6,8}$/i.test(row.swatch)"
            :style="row.swatch && /^#[0-9a-f]{6,8}$/i.test(row.swatch) ? '--rm-swatch: ' + row.swatch : ''"></span>
        <span class="min-w-0" :class="['Text', 'Shadow'].includes(row.label) ? 'truncate' : ''" :title="row.value" x-text="row.value"></span>
    </span>
    <span x-show="selectable" class="w-3.5 shrink-0 text-center text-muted-foreground" aria-hidden="true" x-text="selected ? '✓' : '+'"></span>
    <button type="button" x-show="selectable" class="absolute inset-0 rounded-md"
        :aria-label="(selected ? 'Remove ' : 'Reference ') + row.label + ' ' + row.value"
        :aria-pressed="String(selected)"
        :disabled="!selected && {{ $references }}.length >= {{ \App\Support\ElementTarget::MAX_REFERENCES }}"
        @click="{{ $references }} = toggleElementReference({{ $references }}, reference)"></button>
</div>
