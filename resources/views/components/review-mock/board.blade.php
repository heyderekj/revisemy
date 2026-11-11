@props(['marks'])
@use('App\Models\Annotation')

<div class="grid gap-3 bg-canvas p-3 @sm/mock:grid-cols-2 @lg/mock:p-4 @2xl/mock:grid-cols-4">
    @foreach (Annotation::boardColumnMeta() as $status => $column)
        @php($cards = array_filter($marks, fn ($mark) => $mark['status'] === $status))
        <section class="min-w-0 rounded-xl bg-card p-3">
            <div class="mb-3 flex items-center gap-1.5">
                <flux:icon :name="$column['icon']" variant="micro" class="size-4 shrink-0 text-muted-foreground" />
                <h3 class="flex-1 text-xs font-semibold text-zinc-900">{{ Annotation::statusLabels()[$status] }}</h3>
                <span class="rounded-md bg-chip px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-600">{{ count($cards) }}</span>
            </div>
            @if ($cards)
                <ul class="space-y-2">
                    @foreach ($cards as $index => $mark)
                        <x-review-mock.mark-card :mark="$mark" :number="$index + 1" />
                    @endforeach
                </ul>
            @else
                <p class="rounded-lg border border-dashed border-border p-3 text-xs text-muted-foreground">No marks yet</p>
            @endif
        </section>
    @endforeach
</div>
