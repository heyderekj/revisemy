@php
    $key = ($target['index'] ?? '').':'.$row['label'];
    $attached = in_array($key, $selected, true);
@endphp
@if ($canReference)
    <button type="button" wire:click="{{ $referenceAction }}(@js($key){{ $markId !== null ? ', '.$markId : '' }})"
        aria-pressed="{{ $attached ? 'true' : 'false' }}"
        aria-label="{{ $attached ? 'Remove' : 'Reference' }} {{ $row['label'] }} {{ $row['value'] }}"
        @disabled(! $attached && count($selected) >= \App\Support\ElementTarget::MAX_REFERENCES)
        class="flex min-h-8 w-full items-center gap-2 rounded-md px-2 py-1 text-left hover:bg-chip disabled:opacity-50">
@else
    <div class="flex min-h-8 items-center gap-2 px-2 py-1">
@endif
    <span class="w-[4.25rem] shrink-0 text-muted-foreground">{{ $row['label'] }}</span>
    <span class="flex min-w-0 flex-1 items-center gap-1.5 break-words font-mono text-zinc-700">
        @isset($row['swatch'])
            <span class="size-4 shrink-0 rounded-sm bg-(--rm-swatch) ring-1 ring-black/10" style="--rm-swatch: {{ $row['swatch'] }}" aria-hidden="true"></span>
        @endisset
        <span @class(['min-w-0', 'truncate' => in_array($row['label'], ['Text', 'Shadow'], true)]) title="{{ $row['value'] }}">{{ $row['value'] }}</span>
    </span>
@if ($canReference)
        @if ($attached)
            <flux:icon.check variant="micro" class="size-3.5 shrink-0 text-muted-foreground" />
        @else
            <flux:icon.plus variant="micro" class="size-3.5 shrink-0 text-muted-foreground" />
        @endif
    </button>
@else
    </div>
@endif
