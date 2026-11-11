{{-- One mark, opened: the crop of where it sits, what was said, the part of
     the page, suggested copy and answer, before/after, and what happened.
     Shared by the review page's mark popover and the board's mark sheet.
     Each caller brings its own buttons and reply box (slots), since those
     call its own Livewire methods. The inline review draws the same from
     mcp/partials/mark-detail. --}}
@props(['mark', 'ownerView' => true, 'context' => null, 'compact' => false, 'compare' => false, 'referenceAction' => null, 'selectedReferences' => [], 'referenceMarkId' => null])

<div {{ $attributes->class(['min-w-0', 'flex flex-col gap-2 md:gap-3' => $compact, 'space-y-4' => ! $compact]) }}>
    @if ($compare && $mark->screenshot && $mark->afterScreenshot)
        <x-mark-comparison :mark="$mark" wire:key="mark-comparison-{{ $mark->id }}-{{ $mark->after_screenshot_id }}" />
    @elseif ($mark->screenshot && $compact)
        <details open wire:ignore.self
            class="group/crop order-last md:order-first"
            x-data="{ wide: window.matchMedia('(min-width: 768px)').matches }"
            x-effect="$el.open = wide"
            x-on:resize.window="wide = window.matchMedia('(min-width: 768px)').matches">
            <summary class="flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-lg text-sm font-medium text-muted-foreground hover:text-foreground md:hidden [&::-webkit-details-marker]:hidden">
                <flux:icon.photo variant="micro" class="size-4" />
                Screenshot crop
                <flux:icon.chevron-down variant="micro" class="ml-auto size-4 group-open/crop:rotate-180" />
            </summary>
            <x-mark-focus-preview :mark="$mark" compact />
        </details>
    @elseif ($mark->screenshot)
        <x-mark-focus-preview :mark="$mark" />
    @endif

    <dl @class(['space-y-3 text-sm', 'rounded-xl bg-card p-4 sm:p-5' => ! $compact])>
        <div>
            <dt @class(['text-xs text-muted-foreground', 'sr-only md:not-sr-only' => $compact])>Feedback</dt>
            <dd @class(['leading-relaxed text-pretty whitespace-pre-line text-zinc-800', 'text-[15px] md:mt-1 md:text-sm' => $compact, 'mt-1 sm:text-[15px]' => ! $compact])>{{ $mark->body }}</dd>
            <x-element-reference-chips :references="$mark->element_references" class="mt-2" />
        </div>
        @if ($mark->pdf_anchor)
            <div>
                <dt class="text-xs font-medium text-muted-foreground">Selected text · Page {{ $mark->pdf_anchor['page'] }}</dt>
                <dd class="mt-1 border-l-2 border-key px-2 text-sm text-zinc-700">{{ $mark->pdf_anchor['quote'] }}</dd>
            </div>
        @endif
        @if ($mark->suggested_copy)
            <div>
                <dt class="text-xs text-muted-foreground">Suggested copy</dt>
                <dd class="mt-1 rounded-lg bg-well px-2.5 py-1.5 font-mono text-xs leading-relaxed text-zinc-700">{{ $mark->suggested_copy }}</dd>
            </div>
        @endif
        @if ($mark->question_answer)
            <div>
                <dt class="text-xs text-muted-foreground">Answer</dt>
                <dd class="mt-1 rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs leading-relaxed text-sky-900">{{ $mark->question_answer }}</dd>
            </div>
        @endif
        @if ($mark->target)
            <div>
                <dt class="text-xs text-muted-foreground">On the page</dt>
                <dd class="mt-1 space-y-1.5">
                    <x-element-chip :target="$mark->target" />
                    <x-element-inspect :target="$mark->target" :screenshot="$mark->screenshot" :reference-action="$referenceAction" :selected-references="$selectedReferences" :mark-id="$referenceMarkId" />
                </dd>
            </div>
        @endif
        @if ($context)
            <div>
                <dt class="text-xs text-muted-foreground">What to look at</dt>
                <dd class="mt-1 whitespace-pre-wrap text-pretty text-zinc-700">{{ $context }}</dd>
            </div>
        @endif
        @if ($mark->afterScreenshot && ! $compare)
            <div>
                <dt class="text-xs text-muted-foreground">Before / after</dt>
                <dd class="mt-1"><x-mark-before-after :mark="$mark" /></dd>
            </div>
        @endif
    </dl>

    {{ $slot }}
</div>
