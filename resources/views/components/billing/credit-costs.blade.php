{{-- Compact credit burn reminder. Optional retention row for Plus context. --}}
@props([
    'includeRetention' => true,
    'showLabel' => true,
])

@php
    $burn = config('billing.costs', []);
    $plusRetention = (int) config('billing.plans.pro.review_retention_days', 90);
    $rows = [
        ['icon' => 'photo', 'label' => 'Images / PDF', 'value' => ((int) ($burn['images'] ?? 1)).' credit'],
        ['icon' => 'envelope', 'label' => 'Email HTML', 'value' => ((int) ($burn['html'] ?? 3)).' credits'],
        ['icon' => 'globe-alt', 'label' => 'URL capture', 'value' => ((int) ($burn['capture_url'] ?? 5)).' credits'],
    ];
    if ($includeRetention) {
        $rows[] = ['icon' => 'calendar-days', 'label' => 'Review retention', 'value' => $plusRetention.' days'];
    }
@endphp

<div {{ $attributes }}>
    @if ($showLabel)
        <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400">Credit costs</p>
    @endif
    <dl @class(['space-y-3 text-[14px]', 'mt-4' => $showLabel])>
        @foreach ($rows as $row)
            <div class="flex items-center justify-between gap-4">
                <dt class="flex min-w-0 items-center gap-2.5 text-zinc-600">
                    <x-use-case-icon :name="$row['icon']" size="sm" />
                    <span>{{ $row['label'] }}</span>
                </dt>
                <dd class="shrink-0 font-medium tabular-nums text-zinc-900">{{ $row['value'] }}</dd>
            </div>
        @endforeach
    </dl>
</div>
