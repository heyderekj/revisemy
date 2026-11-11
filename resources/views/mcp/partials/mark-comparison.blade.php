<div class="min-w-0 px-3 sm:px-4">
    <div class="mb-2 inline-flex flex-wrap gap-0.5 rounded-xl bg-well p-0.5" role="group" aria-label="Compare the fix">
        @foreach (['before' => 'Before', 'after' => 'After', 'both' => 'Side by side'] as $mode => $label)
            <button type="button" class="h-8 rounded-lg px-3 text-xs font-medium transition-colors"
                :class="comparisonView === '{{ $mode }}' ? 'bg-raised text-zinc-900 shadow-xs' : 'text-muted-foreground hover:text-zinc-900'"
                :aria-pressed="(comparisonView === '{{ $mode }}').toString()"
                @click="comparisonView = '{{ $mode }}'">{{ $label }}</button>
        @endforeach
    </div>
    <div class="grid gap-2" :class="comparisonView === 'both' ? 'grid-cols-2' : 'grid-cols-1'">
        <figure x-show="comparisonView !== 'after'">
            <figcaption class="mb-1 text-xs text-muted-foreground" x-show="comparisonView === 'both'">Before</figcaption>
            <div class="mx-auto w-full overflow-hidden rounded-xl border border-border bg-well"
                :style="'max-width:calc(min(42dvh,26rem) * ' + activePin.comparison_preview.ratio + ')'">
                <div class="w-full bg-no-repeat" role="img" aria-label="Before — marked area"
                    :style="'aspect-ratio:' + activePin.comparison_preview.ratio + ';' + bgStyle(activePin, activePin.comparison_preview.window)"></div>
            </div>
        </figure>
        <figure x-show="comparisonView !== 'before'">
            <figcaption class="mb-1 text-xs text-muted-foreground" x-show="comparisonView === 'both'">After</figcaption>
            <div class="mx-auto w-full overflow-hidden rounded-xl border border-border bg-well"
                :style="'max-width:calc(min(42dvh,26rem) * ' + activePin.comparison_preview.ratio + ')'">
                <img class="block w-full object-contain" :style="'aspect-ratio:' + activePin.comparison_preview.ratio"
                    :src="activePin.after_screenshot_url" alt="After — submitted screenshot of the fix">
            </div>
        </figure>
    </div>
</div>
