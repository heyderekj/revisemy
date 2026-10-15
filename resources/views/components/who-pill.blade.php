{{-- Who did something: a round pill, beside the square status tag. The
     agent's carries a chip icon. Colours live in Annotation::ACTOR_PILLS. --}}
@props(['actor' => 'owner', 'label' => null])
@php($pill = \App\Models\Annotation::ACTOR_PILLS[$actor] ?? \App\Models\Annotation::ACTOR_PILLS['owner'])
<span {{ $attributes->class(['inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold leading-4 whitespace-nowrap', $pill['pill'], 'pl-1.5' => $actor === 'agent']) }}>@if ($actor === 'agent')<flux:icon.cpu-chip variant="micro" class="size-3" />@endif{{ $label ?? match ($actor) { 'agent' => 'Agent', 'owner' => 'You', default => 'Guest' } }}</span>
