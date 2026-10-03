{{-- Credit burn + Try vs Plus comparison for upgrade / success. --}}
@props([
    /** When true, show Try and Plus value columns (upgrade + confirm). */
    'compare' => false,
    /** Confirm page copy: Was Try / Now Plus. Upgrade: Try vs Plus. */
    'tone' => 'upgrade',
])

@php
    $burn = config('billing.costs', []);
    $tryCredits = (int) config('billing.plans.free.credits', 20);
    $plusCredits = (int) config('billing.plans.pro.credits', 100);
    $tryRetention = (int) config('billing.plans.free.review_retention_days', 7);
    $plusRetention = (int) config('billing.plans.pro.review_retention_days', 90);
    $tryRenews = (bool) config('billing.plans.free.renews', true);
    $tryLabel = $tone === 'confirm' ? 'Was Try' : 'Try';
    $plusLabel = $tone === 'confirm' ? 'Now Plus' : 'Plus';

    $rows = [
        [
            'icon' => 'ticket',
            'label' => 'Credits',
            'free' => $tryRenews ? $tryCredits.'/mo' : $tryCredits.' once',
            'plus' => $plusCredits.'/mo',
            'same' => false,
        ],
        [
            'icon' => 'photo',
            'label' => 'Images / PDF',
            'free' => ((int) ($burn['images'] ?? 1)).' credit',
            'plus' => ((int) ($burn['images'] ?? 1)).' credit',
            'same' => true,
        ],
        [
            'icon' => 'envelope',
            'label' => 'Email HTML',
            'free' => ((int) ($burn['html'] ?? 3)).' credits',
            'plus' => ((int) ($burn['html'] ?? 3)).' credits',
            'same' => true,
        ],
        [
            'icon' => 'globe-alt',
            'label' => 'URL capture',
            'free' => ((int) ($burn['capture_url'] ?? 5)).' credits',
            'plus' => ((int) ($burn['capture_url'] ?? 5)).' credits',
            'same' => true,
        ],
        [
            'icon' => 'calendar-days',
            'label' => 'Review retention',
            'free' => $tryRetention.' days',
            'plus' => $plusRetention.' days',
            'same' => false,
        ],
    ];
@endphp

<div {{ $attributes }}>
    @if ($compare)
        <div class="overflow-hidden rounded-xl bg-card">
            <table class="w-full border-collapse text-left text-[13px] sm:text-[14px]">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50/80">
                        <th scope="col" class="px-3 py-2.5 font-medium text-zinc-500 sm:px-4">
                            <span class="sr-only">Item</span>
                        </th>
                        <th scope="col" class="w-[5.5rem] px-2 py-2.5 text-right text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 sm:w-28 sm:px-4">
                            {{ $tryLabel }}
                        </th>
                        <th scope="col" class="w-[5.5rem] px-2 py-2.5 text-right text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-900 sm:w-28 sm:px-4">
                            {{ $plusLabel }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class(['border-t border-zinc-200' => ! $loop->first])>
                            <th scope="row" class="px-3 py-3 font-normal text-zinc-600 sm:px-4">
                                <span class="flex min-w-0 items-center gap-2.5">
                                    <x-use-case-icon :name="$row['icon']" size="sm" bare />
                                    <span>{{ $row['label'] }}</span>
                                </span>
                            </th>
                            <td @class([
                                'px-2 py-3 text-right tabular-nums sm:px-4',
                                'text-zinc-400 line-through decoration-zinc-300' => ! $row['same'] && $tone === 'confirm',
                                'text-zinc-400' => $row['same'] || ($tone !== 'confirm' && ! $row['same']),
                            ])>
                                {{ $row['free'] }}
                            </td>
                            <td @class([
                                'px-2 py-3 text-right tabular-nums sm:px-4',
                                'font-semibold text-zinc-900' => ! $row['same'],
                                'font-medium text-zinc-500' => $row['same'],
                            ])>
                                {{ $row['plus'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <dl class="space-y-3 text-[14px]">
            @foreach ($rows as $row)
                @continue($row['label'] === 'Credits')
                <div class="flex items-center justify-between gap-4">
                    <dt class="flex min-w-0 items-center gap-2.5 text-zinc-600">
                        <x-use-case-icon :name="$row['icon']" size="sm" bare />
                        <span>{{ $row['label'] }}</span>
                    </dt>
                    {{-- Without the comparison this is the Try pack, so Try's values. --}}
                    <dd class="shrink-0 font-medium tabular-nums text-zinc-900">{{ $row['free'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
