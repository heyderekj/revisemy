{{-- One mark in the sidebar, as review/partials/mark-card draws it. --}}
@props(['mark', 'number'])
@use('App\Models\Annotation')

@php
    $canManage = $mark['severity'] !== Annotation::SEVERITY_KEEP;
    $tone = Annotation::statusTones()[$mark['status']] ?? 'neutral';
    $verify = $canManage && $mark['status'] === Annotation::STATUS_RESOLVED;
    $reopen = $canManage && $mark['status'] !== Annotation::STATUS_OPEN;
    $answer = $mark['severity'] === Annotation::SEVERITY_QUESTION;
@endphp

<li class="rounded-xl bg-raised p-3 shadow-xs ring-1 ring-black/[0.04]">
    <div class="mb-1 flex flex-wrap items-center gap-2">
        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold {{ (new Annotation)->markerClass() }}">M{{ $number }}</span>
        <span class="text-xs text-muted-foreground">{{ Annotation::severityLabels()[$mark['severity']] }}</span>
        <x-signal-tag :tone="$tone">{{ Annotation::statusLabels()[$mark['status']] }}</x-signal-tag>
    </div>

    <p class="text-sm leading-relaxed text-zinc-700">{{ $mark['note'] }}</p>

    {{-- Only the controls that say something about this mark's state. --}}
    @if ($verify || $reopen || $answer)
    <div class="mt-2 flex flex-wrap items-center gap-1.5">
        @if ($verify)
            <span class="inline-flex h-7 items-center gap-1 rounded-full bg-done-soft px-2.5 text-xs font-medium text-done-ink">
                <flux:icon.check variant="micro" class="size-3.5" /> Verify
            </span>
        @endif
        @if ($reopen)
            <span class="inline-flex h-7 items-center rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700">Reopen</span>
        @endif
        @if ($answer)
            <span class="inline-flex h-7 items-center rounded-full bg-chip px-2.5 text-xs font-medium text-zinc-700">Answer</span>
        @endif
    </div>
    @endif
</li>
