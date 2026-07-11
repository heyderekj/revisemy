<?php

namespace App\Services;

use App\Events\MarkUpdated;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Single source of truth for mark (annotation) status transitions.
 *
 * The one rule that matters: agents may only move a mark to in_progress or
 * resolved. Verifying and reopening stay human-only, so human marks remain
 * authoritative even as the agent reports progress.
 */
class MarkLifecycleService
{
    /**
     * Apply a batch of agent updates. Each entry is {id, status?, note?}.
     * Marks are scoped to the workspace, so the agent can only touch its own.
     *
     * @param  array<int, array{id: int|string, status?: string, note?: ?string}>  $marks
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

            $this->transition($annotation, $status, $mark['note'] ?? null);
            $updated->push($annotation);
        }

        return $updated;
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
