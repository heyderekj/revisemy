{{-- One mark, opened: the crop of where it sits, what was said, the part of
     the page, suggested copy and answer, before/after, and what happened.
     Shared by the review page's mark popover and the board's mark sheet.
     Each caller brings its own buttons and reply box (slots), since those
     call its own Livewire methods. The inline review draws the same from
     mcp/partials/mark-detail. --}}
@props(['mark', 'ownerView' => true, 'context' => null, 'compact' => false])

<div {{ $attributes->class(['min-w-0 space-y-4']) }}>
    @if ($mark->screenshot)
        <x-mark-focus-preview :mark="$mark" />
    @endif

    <dl @class(['space-y-3 text-sm', 'rounded-xl bg-card p-4 sm:p-5' => ! $compact])>
        <div>
            <dt class="text-xs text-muted-foreground">Feedback</dt>
            <dd @class(['mt-1 leading-relaxed text-pretty whitespace-pre-line text-zinc-800', 'sm:text-[15px]' => ! $compact])>{{ $mark->body }}</dd>
        </div>
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
                    <x-element-inspect :target="$mark->target" />
                </dd>
            </div>
        @endif
        @if ($context)
            <div>
                <dt class="text-xs text-muted-foreground">What to look at</dt>
                <dd class="mt-1 whitespace-pre-wrap text-pretty text-zinc-700">{{ $context }}</dd>
            </div>
        @endif
        @if ($mark->afterScreenshot)
            <div>
                <dt class="text-xs text-muted-foreground">Before / after</dt>
                <dd class="mt-1"><x-mark-before-after :mark="$mark" /></dd>
            </div>
        @endif
    </dl>

    {{ $slot }}
</div>
