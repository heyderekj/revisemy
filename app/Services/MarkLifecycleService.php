<?php

namespace App\Services;

use App\Events\MarkUpdated;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\AnnotationEvent;
use App\Models\Review;
use App\Models\Screenshot;
use App\Models\Workspace;
use App\Support\FeedbackText;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for mark (annotation) status transitions.
 *
 * Agents may only move a mark to in_progress or resolved. Humans verify,
 * reopen, and may override-resolve on the board. Human marks stay
 * authoritative even as the agent reports progress.
 */
class MarkLifecycleService
{
    /**
     * How many after_image URLs one resolve_marks call will fetch. Matches the
     * 5-screenshot cap on a review; the rest come back in `skipped` so the
     * agent can resend them rather than losing them silently.
     */
    public const MAX_REMOTE_AFTER_IMAGES = 5;

    public function __construct(protected ScreenshotStorage $screenshots) {}

    /**
     * Apply a batch of agent updates. Each entry is {id, status?, note?, after_image?, reply?}.
     * Marks must belong to this review or its parent pass (reopened parent
     * marks are carried over for the agent to fix alongside this pass's).
     *
     * Anything that could not be applied comes back in `skipped` rather than
     * being dropped silently — a partial batch used to look identical to a full
     * one, so an agent would move on believing every mark had landed.
     *
     * A reply posts on the mark's thread as the agent, alongside the status
     * change, so the fix and its explanation land together.
     *
     * @param  array<int, array{id: int|string, status?: string, note?: ?string, after_image?: ?string, reply?: ?string}>  $marks
     * @return array{updated: Collection<int, Annotation>, skipped: list<array{id: int, reason: string, detail: string}>}
     */
    public function applyAgentUpdates(Review $review, array $marks): array
    {
        $ids = collect($marks)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $annotations = $this->workspaceMarks($review->workspace, $ids)->keyBy('id');
        $reviewIds = array_map(intval(...), array_filter([$review->id, $review->parent_id]));

        $updated = collect();
        $skipped = [];
        $remoteFetches = 0;

        foreach ($marks as $mark) {
            $id = (int) ($mark['id'] ?? 0);
            $annotation = $annotations->get($id);

            if (! $annotation) {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'not_found',
                    'detail' => 'No mark with that id on this try token — use work_packets.pins[].id (the database id), not the M# number.',
                ];

                continue;
            }

            if (! in_array((int) $annotation->screenshot->review_id, $reviewIds, true)) {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'wrong_review',
                    'detail' => 'That mark belongs to a different review — resolve it with that review\'s id.',
                ];

                continue;
            }

            if ($annotation->severity === Annotation::SEVERITY_KEEP) {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'keep',
                    'detail' => 'Keep marks mean "do not change this" — there is nothing to resolve.',
                ];

                continue;
            }

            if ($annotation->status === Annotation::STATUS_VERIFIED) {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'already_verified',
                    'detail' => 'The human already verified this fix — leave it alone.',
                ];

                continue;
            }

            $status = $mark['status'] ?? Annotation::STATUS_RESOLVED;

            if (! in_array($status, Annotation::agentStatuses(), true)) {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'invalid_status',
                    'detail' => 'Agents may only set '.implode(' or ', Annotation::agentStatuses()).'; the human verifies.',
                ];

                continue;
            }

            $afterImage = ! empty($mark['after_image']) && is_string($mark['after_image'])
                ? $mark['after_image']
                : null;

            if ($afterImage !== null && self::isRemote($afterImage)) {
                // One 50-mark batch could otherwise drive 50 outbound fetches.
                // Inline sources (data URL / base64) are unlimited — they cost
                // us nothing on the wire.
                if ($remoteFetches >= self::MAX_REMOTE_AFTER_IMAGES) {
                    $skipped[] = [
                        'id' => $id,
                        'reason' => 'after_image_limit',
                        'detail' => 'Only '.self::MAX_REMOTE_AFTER_IMAGES.' after_image URLs are fetched per call — resend this mark in a follow-up resolve_marks, or pass the image as a data URL.',
                    ];

                    continue;
                }

                $remoteFetches++;
            }

            $afterShot = null;

            if ($afterImage !== null) {
                // A bad image skips just this mark. Throwing here used to fail
                // the whole call after earlier marks had already transitioned,
                // so a retry stored their after shots twice.
                try {
                    $afterShot = $this->storeAfterImage($annotation, $afterImage);
                } catch (ValidationException $e) {
                    $skipped[] = [
                        'id' => $id,
                        'reason' => 'invalid_after_image',
                        'detail' => (collect($e->errors())->flatten()->first() ?? 'Could not store that after_image.').' The mark was not updated — resend it with a valid image or without after_image.',
                    ];

                    continue;
                }
            }

            $this->transition($annotation, $status, $mark['note'] ?? null, AnnotationEvent::ACTOR_AGENT, $afterShot?->id);

            $reply = FeedbackText::sanitizeBody((string) ($mark['reply'] ?? ''));

            if ($reply !== '') {
                $this->addComment($annotation, AnnotationEvent::ACTOR_AGENT, $reply);
            }

            $updated->push($annotation);
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Whether this source makes us fetch something. Data URLs and raw base64
     * arrive in the request body; only an http(s) URL costs an outbound call.
     */
    protected static function isRemote(string $image): bool
    {
        $image = trim($image);

        if (str_starts_with($image, 'data:image/')) {
            return false;
        }

        return (bool) filter_var($image, FILTER_VALIDATE_URL);
    }

    /**
     * Attach an "after" screenshot showing the fixed area. Stored directly —
     * bypassing ReviewService::addScreenshot() on purpose: that path rejects
     * closed reviews, but after-evidence arrives exactly while the review sits
     * in changes_requested. After shots are excluded from Review::screenshots(),
     * so they never count against the 5-shot cap or get a second opinion.
     */
    protected function storeAfterImage(Annotation $annotation, string $image): Screenshot
    {
        $annotation->loadMissing('screenshot.review');

        $shot = $this->screenshots->store(
            $annotation->screenshot->review,
            $image,
            sortOrder: 0,
            kind: Screenshot::KIND_AFTER,
        );

        $annotation->update(['after_screenshot_id' => $shot->id]);

        return $shot;
    }

    /**
     * The agent answers on marks' threads. Each entry is {id, body}. A reply
     * never changes a mark's status — the human's marks and verify are the
     * only ones that count — so it is allowed on any mark of this review or
     * the pass before it, including verified ones.
     *
     * @param  array<int, array{id: int|string, body?: ?string}>  $replies
     * @return array{updated: Collection<int, Annotation>, skipped: list<array{id: int, reason: string, detail: string}>}
     */
    public function addAgentReplies(Review $review, array $replies): array
    {
        $ids = collect($replies)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $annotations = $this->workspaceMarks($review->workspace, $ids)->keyBy('id');
        $reviewIds = array_map(intval(...), array_filter([$review->id, $review->parent_id]));

        $updated = collect();
        $skipped = [];

        foreach ($replies as $reply) {
            $id = (int) ($reply['id'] ?? 0);
            $annotation = $annotations->get($id);

            if (! $annotation) {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'not_found',
                    'detail' => 'No mark with that id on this try token — use work_packets.pins[].id (the database id), not the M# number.',
                ];

                continue;
            }

            if (! in_array((int) $annotation->screenshot->review_id, $reviewIds, true)) {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'wrong_review',
                    'detail' => 'That mark belongs to a different review — reply with that review\'s id.',
                ];

                continue;
            }

            $body = FeedbackText::sanitizeBody((string) ($reply['body'] ?? ''));

            if ($body === '') {
                $skipped[] = [
                    'id' => $id,
                    'reason' => 'empty',
                    'detail' => 'The reply was empty after cleaning — send the words you want the human to read.',
                ];

                continue;
            }

            $this->addComment($annotation, AnnotationEvent::ACTOR_AGENT, $body);
            $updated->push($annotation);
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Post on a mark's thread. $kind is who is speaking (owner, guest or
     * agent); a guest's name is their $author, the others have fixed names.
     */
    public function addComment(Annotation $annotation, string $kind, string $body, ?string $author = null): AnnotationComment
    {
        $comment = $annotation->comments()->create([
            'author' => match ($kind) {
                AnnotationEvent::ACTOR_OWNER => 'Owner',
                AnnotationEvent::ACTOR_AGENT => 'Agent',
                default => $author ?: 'Guest',
            },
            'from_owner' => $kind === AnnotationEvent::ACTOR_OWNER,
            'author_kind' => $kind,
            'body' => $body,
        ]);

        // Touch the mark so updated_at moves and pollers see something new.
        $annotation->touch();

        MarkUpdated::dispatch($annotation);

        return $comment;
    }

    /**
     * Human drops a new mark on a screenshot. Coordinates are normalized
     * (0–1); an optional area is a top-left {x, y, w, h} rectangle clamped
     * inside the image. Shared by the review page and the MCP app's
     * add_mark tool. Broadcasts so open review pages refresh live.
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $area
     * @param  array{suggested_copy?: ?string, source?: string, promoted_from_finding_id?: ?int}  $options
     */
    public function createMark(
        Screenshot $screenshot,
        float $x,
        float $y,
        ?array $area,
        string $severity,
        string $body,
        array $options = [],
    ): Annotation {
        if ($area !== null) {
            $area = [
                'x' => max(0.0, min(1.0 - (float) $area['w'], (float) $area['x'])),
                'y' => max(0.0, min(1.0 - (float) $area['h'], (float) $area['y'])),
                'w' => (float) $area['w'],
                'h' => (float) $area['h'],
            ];
        }

        $source = $options['source'] ?? Annotation::SOURCE_HUMAN;

        if (! in_array($source, Annotation::sources(), true)) {
            $source = Annotation::SOURCE_HUMAN;
        }

        $suggestedCopy = $options['suggested_copy'] ?? null;
        $suggestedCopy = is_string($suggestedCopy) && trim($suggestedCopy) !== ''
            ? trim($suggestedCopy)
            : null;

        $annotation = $screenshot->annotations()->create([
            'x' => max(0.0, min(1.0, $x)),
            'y' => max(0.0, min(1.0, $y)),
            'area' => $area,
            'severity' => $severity,
            'body' => $body,
            'suggested_copy' => $suggestedCopy,
            'source' => $source,
            'promoted_from_finding_id' => $options['promoted_from_finding_id'] ?? null,
            'number' => $screenshot->review->nextMarkNumber(),
        ]);

        MarkUpdated::dispatch($annotation);

        return $annotation;
    }

    /**
     * Record the human's answer on a question mark so the agent can act on it.
     */
    public function answerQuestion(Annotation $annotation, string $answer): bool
    {
        if ($annotation->severity !== Annotation::SEVERITY_QUESTION) {
            return false;
        }

        $answer = trim($answer);

        if ($answer === '') {
            return false;
        }

        $annotation->update(['question_answer' => $answer]);

        MarkUpdated::dispatch($annotation);

        return true;
    }

    /**
     * Verify every resolved mark on this review (owner batch action).
     */
    public function verifyAllResolved(Review $review): int
    {
        $count = 0;

        // This pass and the one before it: the review shows both, so "Verify all" means both.
        collect([$review, $review->parent])
            ->filter()
            ->flatMap(fn (Review $pass) => $pass->annotations()->where('status', Annotation::STATUS_RESOLVED)->get())
            ->each(function (Annotation $annotation) use (&$count): void {
                if ($this->verify($annotation)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Human verifies a resolved mark. No-op unless it is currently resolved.
     */
    public function verify(Annotation $annotation): bool
    {
        if ($annotation->status !== Annotation::STATUS_RESOLVED) {
            return false;
        }

        $this->transition($annotation, Annotation::STATUS_VERIFIED);

        return true;
    }

    /**
     * Human reopens a mark from any status back to open.
     */
    public function reopen(Annotation $annotation): bool
    {
        if ($annotation->status === Annotation::STATUS_OPEN) {
            return false;
        }

        $this->transition($annotation, Annotation::STATUS_OPEN);

        return true;
    }

    /**
     * Human marks a fix resolved on the board without waiting on the agent.
     */
    public function resolveByOwner(Annotation $annotation, ?string $note = null): bool
    {
        if (! in_array($annotation->status, [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS], true)) {
            return false;
        }

        $this->transition($annotation, Annotation::STATUS_RESOLVED, $note);

        return true;
    }

    /**
     * On approval, promote every resolved mark on a review to verified.
     */
    public function verifyResolvedForReview(Review $review): int
    {
        $ids = $review->annotations()
            ->where('status', Annotation::STATUS_RESOLVED)
            ->pluck('annotations.id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $now = now();

        Annotation::query()->whereKey($ids)->update([
            'status' => Annotation::STATUS_VERIFIED,
            'verified_at' => $now,
        ]);

        // Approving verifies them, so the timeline says the owner did.
        AnnotationEvent::query()->insert($ids->map(fn ($id) => [
            'annotation_id' => (int) $id,
            'actor' => AnnotationEvent::ACTOR_OWNER,
            'type' => AnnotationEvent::TYPE_VERIFIED,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        return $ids->count();
    }

    /**
     * Move a mark and record who moved it. A repeat of the same status with
     * nothing new (no note, no after image) records nothing, so an agent
     * re-sending in_progress does not stack "started" on the timeline.
     */
    protected function transition(
        Annotation $annotation,
        string $status,
        ?string $note = null,
        string $actor = AnnotationEvent::ACTOR_OWNER,
        ?int $afterScreenshotId = null,
    ): void {
        $note = $note !== null ? trim($note) : null;
        $changed = $annotation->status !== $status;

        $attributes = ['status' => $status];

        if ($note !== null && $note !== '') {
            $attributes['resolution_note'] = $note;
        }

        $attributes['resolved_at'] = $status === Annotation::STATUS_RESOLVED ? now() : null;
        $attributes['verified_at'] = $status === Annotation::STATUS_VERIFIED ? now() : $annotation->verified_at;

        if ($status === Annotation::STATUS_OPEN) {
            $attributes['verified_at'] = null;
        }

        $annotation->update($attributes);

        $type = AnnotationEvent::typeForStatus($status);

        if ($type !== null && ($changed || ($note !== null && $note !== '') || $afterScreenshotId !== null)) {
            $annotation->events()->create([
                'actor' => $actor,
                'type' => $type,
                'body' => $note !== '' ? $note : null,
                'after_screenshot_id' => $afterScreenshotId,
            ]);
        }

        MarkUpdated::dispatch($annotation);
    }

    /**
     * Load annotations by id, scoped to a workspace via screenshot → review.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Annotation>
     */
    protected function workspaceMarks(Workspace $workspace, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Annotation::query()
            ->whereKey($ids)
            ->whereHas('screenshot.review', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with('screenshot')
            ->get();
    }
}
