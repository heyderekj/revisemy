{{-- One hint: second opinion (S, owner only) or a guest's suggestion (G).
     Suggestions until the owner accepts them as marks. --}}
@php($guest = $finding->isGuest())
@php($number = $guest ? ($suggestionNumbers['g'][$finding->id] ?? '') : ($suggestionNumbers['s'][$finding->id] ?? ''))
<li
    id="fb-finding-{{ $finding->id }}"
    class="cursor-pointer rounded-xl bg-raised p-3 shadow-xs ring-1 ring-black/[0.04] transition-shadow"
    x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
    x-on:click="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
    x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} && '!ring-2 {{ $guest ? '!ring-zinc-400' : '!ring-sky-400' }}'"
>
    <div class="mb-1 flex items-start gap-2">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <span @class([
                'flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full border border-dashed px-1 text-xs font-semibold',
                'border-zinc-500 text-zinc-700' => $guest,
                'border-sky-500 text-sky-700' => ! $guest,
            ])>{{ $guest ? 'G' : 'S' }}{{ $number }}</span>
            <span class="text-xs text-muted-foreground">{{ $guest ? $finding->sourceLabel() : (\App\Models\Annotation::allSeverityLabels()[$finding->severity] ?? ucfirst($finding->severity)) }}</span>
            @if (! $guest && $finding->isVisionSource())
                <span class="rounded-md bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-800">{{ $finding->sourceLabel() }}</span>
            @endif
        </div>
        @if ($review->isOpenForFeedback() && $mode === 'owner')
            <div class="flex shrink-0 items-center gap-1" x-on:click.stop x-data="{ open: false }">
                <div class="relative">
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        aria-label="Accept as a mark"
                        title="Accept as a mark"
                        class="inline-flex h-7 items-center gap-1 rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover"
                    ><flux:icon.check variant="micro" class="size-3.5" /> Accept</button>
                    <div
                        x-show="open"
                        x-cloak
                        x-on:click.outside="open = false"
                        class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-xl bg-lift py-1 shadow-lg ring-1 ring-black/[0.07]"
                    >
                        <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-chip" wire:click="acceptFinding({{ $finding->id }})" x-on:click="open = false">As {{ \App\Models\Annotation::allSeverityLabels()[$finding->pinSeverity()] ?? $finding->pinSeverity() }}</button>
                        @foreach (\App\Models\Annotation::severityLabels() as $sev => $sevLabel)
                            @if ($sev !== \App\Models\Annotation::SEVERITY_KEEP && $sev !== $finding->pinSeverity())
                                <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-700 hover:bg-chip" wire:click="acceptFinding({{ $finding->id }}, '{{ $sev }}')" x-on:click="open = false">As {{ $sevLabel }}</button>
                            @endif
                        @endforeach
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="dismissFinding({{ $finding->id }})"
                    aria-label="Dismiss"
                    title="Dismiss"
                    class="inline-flex size-7 items-center justify-center rounded-full text-zinc-400 transition-colors hover:bg-chip hover:text-zinc-700"
                ><flux:icon.x-mark variant="micro" class="size-3.5" /></button>
            </div>
        @endif
    </div>
    <p class="text-sm leading-relaxed text-zinc-700">{{ $finding->body }}</p>
</li>
