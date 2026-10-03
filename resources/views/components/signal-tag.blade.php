{{-- A state, said the way Koati says it: a soft wash with a solid dot, squarer
     than anything you can press. Tones live in App\Models\Annotation::TONES. --}}
@props(['tone' => 'neutral'])
@php($t = \App\Models\Annotation::TONES[$tone] ?? \App\Models\Annotation::TONES['neutral'])
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-4', $t['tag']]) }}><span class="size-1.5 shrink-0 rounded-full {{ $t['dot'] }}" aria-hidden="true"></span>{{ $slot }}</span>
