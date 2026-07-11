@props([
    'variant' => 'outline',
])

@php
$classes = Flux::classes('shrink-0')
    ->add(match($variant) {
        'outline' => '[:where(&)]:size-6',
        'solid' => '[:where(&)]:size-6',
        'mini' => '[:where(&)]:size-5',
        'micro' => '[:where(&)]:size-4',
    });
@endphp

<svg {{ $attributes->class($classes) }} data-flux-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" data-slot="icon">
  <path d="M18.03 6.53a.75.75 0 0 0-1.06-1.06l-6.363 6.364 1.06 1.06 6.363-6.363Zm4.243-.001a.75.75 0 1 0-1.061-1.06L11.667 14.98 7.53 10.844a.75.75 0 0 0-1.06 1.06l4.666 4.667a.75.75 0 0 0 1.061 0L22.273 6.528ZM1.47 11.905a.75.75 0 0 1 1.06 0l4.667 4.666a.75.75 0 0 1-1.06 1.06L1.47 12.966a.75.75 0 0 1 0-1.06Z"/>
</svg>
