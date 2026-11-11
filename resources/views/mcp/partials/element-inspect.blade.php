<details class="group rounded-lg bg-well text-xs" x-show="{{ $source }}?.rows?.length" data-element-inspect>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 font-medium text-zinc-700 hover:text-zinc-900 [&::-webkit-details-marker]:hidden">
        Inspect
        <span class="text-zinc-400 transition group-open:rotate-180" aria-hidden="true">⌄</span>
    </summary>
    <div class="border-t border-border p-1">
        <template x-for="row in ({{ $source }}?.rows || []).filter(row => !row.group)" :key="row.label">
            @include('mcp.partials.element-inspect-row')
        </template>
        <details class="border-t border-border" x-show="{{ $source }}?.rows?.some(row => row.group)">
            <summary class="cursor-pointer rounded-md px-2 py-2 text-muted-foreground hover:bg-chip">More properties</summary>
            <template x-for="(row, index) in ({{ $source }}?.rows || []).filter(row => row.group)" :key="row.label">
                <div>
                    <p class="px-2 pt-2 pb-0.5 font-medium text-muted-foreground"
                        x-show="index === 0 || ({{ $source }}?.rows || []).filter(item => item.group)[index - 1]?.group !== row.group"
                        x-text="row.group"></p>
                    @include('mcp.partials.element-inspect-row')
                </div>
            </template>
        </details>
    </div>
</details>
