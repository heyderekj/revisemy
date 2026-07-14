@props([
    'name',
    'size' => 'md',
])

@php
    $shell = match ($size) {
        'sm' => 'size-7 rounded-md',
        'lg' => 'size-11 rounded-xl',
        default => 'size-9 rounded-lg',
    };
    $glyph = match ($size) {
        'sm' => 'size-3.5',
        'lg' => 'size-5',
        default => 'size-[18px]',
    };
@endphp

<div {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200',
    $shell,
]) }}>
    @switch($name)
        @case('device-phone-mobile')
            <flux:icon.device-phone-mobile variant="micro" class="{{ $glyph }}" />
            @break
        @case('globe-alt')
            <flux:icon.globe-alt variant="micro" class="{{ $glyph }}" />
            @break
        @case('envelope')
            <flux:icon.envelope variant="micro" class="{{ $glyph }}" />
            @break
        @case('presentation-chart-bar')
            <flux:icon.presentation-chart-bar variant="micro" class="{{ $glyph }}" />
            @break
        @case('photo')
            <flux:icon.photo variant="micro" class="{{ $glyph }}" />
            @break
        @case('document')
            <flux:icon.document variant="micro" class="{{ $glyph }}" />
            @break
        @case('code-bracket')
            <flux:icon.code-bracket variant="micro" class="{{ $glyph }}" />
            @break
        @case('cursor-arrow-rays')
            <flux:icon.cursor-arrow-rays variant="micro" class="{{ $glyph }}" />
            @break
        @case('arrows-right-left')
            <flux:icon.arrows-right-left variant="micro" class="{{ $glyph }}" />
            @break
        @case('light-bulb')
            <flux:icon.light-bulb variant="micro" class="{{ $glyph }}" />
            @break
        @case('link')
            <flux:icon.link variant="micro" class="{{ $glyph }}" />
            @break
        @case('computer-desktop')
            <flux:icon.computer-desktop variant="micro" class="{{ $glyph }}" />
            @break
        @case('eye')
            <flux:icon.eye variant="micro" class="{{ $glyph }}" />
            @break
        @case('users')
            <flux:icon.users variant="micro" class="{{ $glyph }}" />
            @break
        @case('arrow-path')
            <flux:icon.arrow-path variant="micro" class="{{ $glyph }}" />
            @break
        @case('swatch')
            <flux:icon.swatch variant="micro" class="{{ $glyph }}" />
            @break
        @case('check')
            <flux:icon.check variant="micro" class="{{ $glyph }}" />
            @break
        @case('queue-list')
            <flux:icon.queue-list variant="micro" class="{{ $glyph }}" />
            @break
        @case('puzzle-piece')
            <flux:icon.puzzle-piece variant="micro" class="{{ $glyph }}" />
            @break
        @case('squares-2x2')
            <flux:icon.squares-2x2 variant="micro" class="{{ $glyph }}" />
            @break
        @case('paint-brush')
            <flux:icon.paint-brush variant="micro" class="{{ $glyph }}" />
            @break
        @case('clipboard-document-list')
            <flux:icon.clipboard-document-list variant="micro" class="{{ $glyph }}" />
            @break
        @case('command-line')
            <flux:icon.command-line variant="micro" class="{{ $glyph }}" />
            @break
        @case('rocket-launch')
            <flux:icon.rocket-launch variant="micro" class="{{ $glyph }}" />
            @break
        @case('building-office-2')
            <flux:icon.building-office-2 variant="micro" class="{{ $glyph }}" />
            @break
        @default
            <flux:icon.photo variant="micro" class="{{ $glyph }}" />
    @endswitch
</div>
