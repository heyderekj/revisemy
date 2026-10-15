{{-- Everything that happened on a mark, oldest first, each step with who
     took it (App\Support\MarkTimeline). "Talk only" folds the bare state
     changes away and leaves the conversation. The inline review draws the
     same list from pin.timeline in mcp/partials/mark-timeline. A mark with
     nothing on it yet but itself draws nothing. --}}
@props(['mark', 'ownerView' => true])
@php
    $entries = \App\Support\MarkTimeline::for($mark);
    $hasSteps = collect($entries)->contains(fn ($e) => ! \App\Support\MarkTimeline::isTalk($e) && $e['kind'] !== \App\Support\MarkTimeline::KIND_MARKED);
@endphp
@if (count($entries) > 1)
<div {{ $attributes->class(['min-w-0']) }} x-data="{ talk: false }">
    @if ($hasSteps)
        <div class="mb-2 inline-flex gap-0.5 rounded-full bg-well p-0.5" role="group" aria-label="Show on the timeline">
            <button type="button" class="h-6 rounded-full px-2.5 text-[11px] font-medium transition-colors" x-bind:class="talk ? 'text-muted-foreground hover:text-zinc-900' : 'bg-raised text-zinc-900 shadow-xs'" x-on:click="talk = false" x-bind:aria-pressed="!talk">Everything</button>
            <button type="button" class="h-6 rounded-full px-2.5 text-[11px] font-medium transition-colors" x-bind:class="talk ? 'bg-raised text-zinc-900 shadow-xs' : 'text-muted-foreground hover:text-zinc-900'" x-on:click="talk = true" x-bind:aria-pressed="talk">Talk only</button>
        </div>
    @endif
    <ol class="min-w-0">
        @foreach ($entries as $entry)
            @php($talk = \App\Support\MarkTimeline::isTalk($entry) || $entry['kind'] === \App\Support\MarkTimeline::KIND_MARKED)
            @php($dot = (\App\Models\Annotation::ACTOR_PILLS[$entry['actor']] ?? \App\Models\Annotation::ACTOR_PILLS['owner'])['dot'])
            <li class="flex gap-2.5" @unless ($talk) x-show="!talk" @endunless>
                <span class="flex w-3 shrink-0 flex-col items-center" aria-hidden="true">
                    <span @class(['mt-1.5 shrink-0 rounded-full', $dot, 'size-2.5' => $talk, 'size-1.5 mt-2' => ! $talk])></span>
                    @unless ($loop->last)
                        <span class="mt-1 w-0.5 grow rounded-full bg-zinc-200"></span>
                    @endunless
                </span>
                <div class="min-w-0 flex-1 pb-3">
                    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                        <x-who-pill :actor="$entry['actor']" :label="\App\Support\MarkTimeline::label($entry, $ownerView)" />
                        <span class="text-zinc-600">{{ $entry['verb'] }}</span>
                        @if ($entry['at'])
                            <time class="text-zinc-400" datetime="{{ $entry['at']->toIso8601String() }}" title="{{ $entry['at']->timezone(config('app.timezone'))->toDayDateTimeString() }}">· {{ $entry['at']->diffForHumans() }}</time>
                        @endif
                    </p>
                    @if ($entry['body'])
                        <p @class([
                            'mt-1 rounded-xl px-2.5 py-1.5 text-sm leading-relaxed text-pretty whitespace-pre-line text-zinc-800',
                            'bg-sky-50' => $entry['actor'] === 'agent',
                            'bg-well' => $entry['actor'] !== 'agent',
                        ])>{{ $entry['body'] }}</p>
                    @endif
                    {{-- The card's before/after already shows the latest one. --}}
                    @if ($entry['after_screenshot_url'] && $entry['after_screenshot_id'] !== $mark->after_screenshot_id)
                        <a href="{{ $entry['after_screenshot_url'] }}" target="_blank" rel="noopener" class="mt-1.5 inline-block">
                            <img src="{{ $entry['after_screenshot_url'] }}" alt="After image from {{ \App\Support\MarkTimeline::label($entry, $ownerView) }}" class="h-12 w-20 rounded-lg object-cover ring-1 ring-emerald-300" loading="lazy" />
                        </a>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
@endif
