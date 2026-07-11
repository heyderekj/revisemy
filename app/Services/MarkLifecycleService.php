<?php

namespace App\Services;

use App\Events\MarkUpdated;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\Screenshot;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Single source of truth for mark (annotation) status transitions.
 *
 * Agents may only move a mark to in_progress or resolved. Humans verify,
 * reopen, and may override-resolve on the board. Human marks stay
 * authoritative even as the agent reports progress.
 */
class MarkLifecycleService
{
    public function __construct(protected ScreenshotStorage $screenshots) {}

    /**
     * Apply a batch of agent updates. Each entry is {id, status?, note?, after_image?}.
     * Marks are scoped to the workspace, so the agent can only touch its own.
     *
     * @param  array<int, array{id: int|string, status?: string, note?: ?string, after_image?: ?string}>  $marks
     * @return Collection<int, Annotation> the annotations that were updated
     */
    public function applyAgentUpdates(Workspace $workspace, array $marks): Collection
    {
        $ids = collect($marks)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $annotations = $this->workspaceMarks($workspace, $ids)->keyBy('id');

        $updated = collect();

        foreach ($marks as $mark) {
            $annotation = $annotations->get((int) ($mark['id'] ?? 0));

            if (! $annotation) {
                continue;
            }

            $status = $mark['status'] ?? Annotation::STATUS_RESOLVED;

            if (! in_array($status, Annotation::agentStatuses(), true)) {
                continue;
            }

            if (! empty($mark['after_image']) && is_string($mark['after_image'])) {
                $this->storeAfterImage($annotation, $mark['after_image']);
            }

            $this->transition($annotation, $status, $mark['note'] ?? null);
            $updated->push($annotation);
        }

        return $updated;
    }

    /**
     * Attach an "after" screenshot showing the fixed area. Stored directly —
     * bypassing ReviewService::addScreenshot() on purpose: that path rejects
     * closed reviews, but after-evidence arrives exactly while the review sits
     * in changes_requested. After shots are excluded from Review::screenshots(),
     * so they never count against the 5-shot cap or get a second opinion.
     */
    protected function storeAfterImage(Annotation $annotation, string $image): void
    {
        $annotation->loadMissing('screenshot.review');

        $shot = $this->screenshots->store(
            $annotation->screenshot->review,
            $image,
            sortOrder: 0,
            kind: Screenshot::KIND_AFTER,
        );

        $annotation->update(['after_screenshot_id' => $shot->id]);
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
        return $review->annotations()
            ->where('status', Annotation::STATUS_RESOLVED)
            ->update([
                'status' => Annotation::STATUS_VERIFIED,
                'verified_at' => now(),
            ]);
    }

    protected function transition(Annotation $annotation, string $status, ?string $note = null): void
    {
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
            ->get();
    }
}
