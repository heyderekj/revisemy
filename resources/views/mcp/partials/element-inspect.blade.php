{{-- What the page says about the element, as on the web
     (components/element-inspect): rows from ElementTarget::inspectRows. A
     colour is the page's own, so its swatch takes it as a variable, and only
     a hex one at that. --}}
<details class="group rounded-lg bg-well text-xs" x-show="{{ $source }} && {{ $source }}.rows && {{ $source }}.rows.length" data-element-inspect>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-2.5 py-1.5 font-medium text-zinc-600 [&::-webkit-details-marker]:hidden">
        Inspect
        <span class="text-zinc-400 transition group-open:rotate-180" aria-hidden="true">⌄</span>
    </summary>
    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 px-2.5 pb-2">
        <template x-for="row in ({{ $source }} && {{ $source }}.rows) || []" :key="row.label">
            <div class="contents">
                <dt class="text-muted-foreground" x-text="row.label"></dt>
                <dd class="flex min-w-0 items-center gap-1.5 font-mono text-zinc-700">
                    <span class="size-3 shrink-0 rounded-sm bg-(--rm-swatch) ring-1 ring-black/10" aria-hidden="true"
                        x-show="row.swatch && /^#[0-9a-f]{6,8}$/i.test(row.swatch)"
                        :style="row.swatch && /^#[0-9a-f]{6,8}$/i.test(row.swatch) ? '--rm-swatch: ' + row.swatch : ''"></span>
                    <span class="truncate select-all" x-text="row.value"></span>
                </dd>
            </div>
        </template>
    </dl>
</details>
