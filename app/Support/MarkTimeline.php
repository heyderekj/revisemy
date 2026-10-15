<?php

namespace App\Support;

use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\AnnotationEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything that happened on one mark, in order: the mark itself, every
 * state change and every comment, each with who did it. The web review, the
 * board, the inline review and the agent payload all read this one list, so
 * they can't disagree about who did what.
 *
 * Marks from before events were recorded get their steps rebuilt from the
 * timestamps they carry (resolved_at, verified_at), credited the way the old
 * "Agent:" note label credited them.
 */
class MarkTimeline
{
    public const KIND_MARKED = 'marked';

    public const KIND_COMMENT = 'comment';

    /**
     * @return list<array{kind: string, actor: string, author: ?string, verb: string, body: ?string, after_screenshot_id: ?int, after_screenshot_url: ?string, at: ?Carbon}>
     */
    public static function for(Annotation $mark): array
    {
        $entries = [[
            'kind' => self::KIND_MARKED,
            'actor' => AnnotationEvent::ACTOR_OWNER,
            'author' => null,
            'verb' => self::markedVerb($mark),
            'body' => null,
            'after_screenshot_id' => null,
            'after_screenshot_url' => null,
            'at' => $mark->created_at,
            'rank' => 0,
        ]];

        $events = $mark->relationLoaded('events') ? $mark->events : $mark->events()->with('afterScreenshot')->get();

        if ($events->isEmpty()) {
            $events = self::legacyEvents($mark);
        }

        foreach ($events as $event) {
            $after = $event->after_screenshot_id
                ? ($event->relationLoaded('afterScreenshot') ? $event->afterScreenshot : $event->afterScreenshot()->first())
                : null;

            $entries[] = [
                'kind' => $event->type,
                'actor' => $event->actor,
                'author' => null,
                'verb' => self::eventVerb($event->type, $after !== null),
                'body' => $event->body,
                'after_screenshot_id' => $after?->id,
                'after_screenshot_url' => $after?->url(),
                'at' => $event->created_at,
                'rank' => 1,
            ];
        }

        $comments = $mark->relationLoaded('comments') ? $mark->comments : $mark->comments()->get();

        foreach ($comments as $comment) {
            /** @var AnnotationComment $comment */
            $entries[] = [
                'kind' => self::KIND_COMMENT,
                'actor' => $comment->author_type ?: ($comment->from_owner ? AnnotationComment::AUTHOR_OWNER : AnnotationComment::AUTHOR_GUEST),
                'author' => $comment->author_type === AnnotationComment::AUTHOR_GUEST ? $comment->author : null,
                'verb' => $comment->isAgent() ? 'replied' : 'commented',
                'body' => $comment->body,
                'after_screenshot_id' => null,
                'after_screenshot_url' => null,
                'at' => $comment->created_at,
                'rank' => 2,
            ];
        }

        // The mark always opens it; then oldest first. Within the same second
        // a state change comes before the reply that explains it, and the
        // order things were added holds.
        $order = array_keys($entries);
        array_multisort(
            array_map(fn (array $e) => $e['kind'] === self::KIND_MARKED ? PHP_INT_MIN : ($e['at']?->getTimestamp() ?? 0), $entries),
            array_column($entries, 'rank'),
            $order,
            $entries,
        );

        return array_map(fn (array $e) => array_diff_key($e, ['rank' => true]), $entries);
    }

    /**
     * Whether a person spoke last and the agent has not answered since.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    public static function awaitingReply(array $entries): bool
    {
        $waiting = false;

        foreach ($entries as $entry) {
            if ($entry['actor'] === AnnotationEvent::ACTOR_AGENT) {
                $waiting = false;
            } elseif ($entry['kind'] === self::KIND_COMMENT) {
                $waiting = true;
            }
        }

        return $waiting;
    }

    /**
     * The timeline as the agent payload and the inline review read it.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    public static function toArray(array $entries): array
    {
        return array_map(fn (array $e) => ['at' => $e['at']?->toIso8601String()] + $e, $entries);
    }

    /**
     * Who to name on an entry. The owner reads "You" on their own review;
     * a guest sees the same person as "Owner".
     *
     * @param  array<string, mixed>  $entry
     */
    public static function label(array $entry, bool $ownerView = true): string
    {
        return match ($entry['actor']) {
            AnnotationEvent::ACTOR_OWNER => $ownerView ? 'You' : 'Owner',
            AnnotationEvent::ACTOR_AGENT => 'Agent',
            default => $entry['author'] ?: 'Guest',
        };
    }

    /**
     * Whether an entry is talk (shown under "Talk only") rather than a bare
     * state change. A state change that came with a note counts as talk.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function isTalk(array $entry): bool
    {
        return $entry['kind'] === self::KIND_COMMENT || ($entry['body'] ?? null) !== null;
    }

    protected static function markedVerb(Annotation $mark): string
    {
        return match ($mark->source) {
            Annotation::SOURCE_AGENT => 'kept a hint from your agent',
            Annotation::SOURCE_GUEST => 'kept a guest\'s suggestion',
            Annotation::SOURCE_CHECKLIST, Annotation::SOURCE_VISION => 'kept a second-opinion hint',
            default => 'marked it',
        };
    }

    protected static function eventVerb(string $type, bool $withAfter): string
    {
        return match ($type) {
            AnnotationEvent::TYPE_STARTED => 'started',
            AnnotationEvent::TYPE_RESOLVED => $withAfter ? 'resolved it, with an after image' : 'resolved it',
            AnnotationEvent::TYPE_REOPENED => 'reopened it',
            AnnotationEvent::TYPE_VERIFIED => 'verified it',
            default => $type,
        };
    }

    /**
     * @return Collection<int, AnnotationEvent>
     */
    protected static function legacyEvents(Annotation $mark): Collection
    {
        $events = collect();

        if ($mark->status === Annotation::STATUS_IN_PROGRESS) {
            $events->push(self::synthetic(AnnotationEvent::ACTOR_AGENT, AnnotationEvent::TYPE_STARTED, $mark->updated_at));
        }

        if ($mark->resolved_at !== null || ($mark->status === Annotation::STATUS_VERIFIED && $mark->resolution_note)) {
            $event = self::synthetic(AnnotationEvent::ACTOR_AGENT, AnnotationEvent::TYPE_RESOLVED, $mark->resolved_at ?? $mark->verified_at, $mark->resolution_note);
            $event->after_screenshot_id = $mark->after_screenshot_id;

            if ($mark->relationLoaded('afterScreenshot')) {
                $event->setRelation('afterScreenshot', $mark->afterScreenshot);
            }

            $events->push($event);
        }

        if ($mark->verified_at !== null && $mark->severity !== Annotation::SEVERITY_KEEP) {
            $events->push(self::synthetic(AnnotationEvent::ACTOR_OWNER, AnnotationEvent::TYPE_VERIFIED, $mark->verified_at));
        }

        return $events;
    }

    protected static function synthetic(string $actor, string $type, ?Carbon $at, ?string $body = null): AnnotationEvent
    {
        $event = new AnnotationEvent(['actor' => $actor, 'type' => $type, 'body' => $body]);
        $event->created_at = $at;

        return $event;
    }
}
