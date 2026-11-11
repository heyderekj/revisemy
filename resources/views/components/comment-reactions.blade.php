@props(['comment', 'ownerView', 'canReact'])

@php
    $actor = \App\Models\AnnotationComment::reactionActor($ownerView);
    $reactions = $comment->reactionSummary($actor);
@endphp

<div class="mt-1.5 flex flex-wrap items-center gap-1" wire:key="comment-reactions-{{ $comment->id }}">
    @foreach (\App\Models\AnnotationComment::REACTIONS as $reaction => [$emoji, $label])
        @php
            $count = $reactions[$reaction]['count'] ?? 0;
            $selected = ($reactions[$reaction]['selected'] ?? false);
            $action = "setCommentReaction({$comment->id}, '{$reaction}', ".($selected ? 'false' : 'true').")";
        @endphp
        @if ($count > 0)
            <button
                type="button"
                @class(['inline-flex h-8 items-center gap-1 rounded-full px-2 text-xs tabular-nums transition-colors md:h-6', 'bg-key/20 ring-1 ring-inset ring-key/60' => $selected, 'bg-chip hover:bg-chip-hover' => ! $selected])
                aria-label="{{ $label }} ({{ $count }})"
                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                wire:click="{{ $action }}"
                wire:loading.attr="disabled"
                wire:target="{{ $action }}"
                @disabled(! $canReact)
            ><span aria-hidden="true">{{ $emoji }}</span> {{ $count }}</button>
        @endif
    @endforeach

    @if ($canReact)
        <flux:dropdown position="top" align="start">
            <button type="button" class="inline-flex size-8 items-center justify-center rounded-full text-zinc-500 transition-colors hover:bg-chip hover:text-zinc-900 md:size-6" aria-label="Add reaction">
                <flux:icon.face-smile variant="micro" class="size-4" />
            </button>
            <flux:menu>
                @foreach (\App\Models\AnnotationComment::REACTIONS as $reaction => [$emoji, $label])
                    @unless (($reactions[$reaction]['selected'] ?? false))
                        <flux:menu.item wire:click="setCommentReaction({{ $comment->id }}, '{{ $reaction }}', true)">
                            <span aria-hidden="true">{{ $emoji }}</span> {{ $label }}
                        </flux:menu.item>
                    @endunless
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
