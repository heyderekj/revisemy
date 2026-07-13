<?php

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Review;
use App\Models\Screenshot;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\SecondOpinionService;
use App\Support\FeedbackText;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $token;

    #[Locked]
    public string $mode = 'owner';

    public Review $review;

    public string $guestName = '';

    public int $activeScreenshotIndex = 0;

    public string $draftBody = '';

    public string $draftSeverity = Annotation::SEVERITY_MUST_FIX;

    public ?float $pendingX = null;

    public ?float $pendingY = null;

    public ?float $pendingW = null;

    public ?float $pendingH = null;

    public string $decisionNote = '';

    public string $contextDraft = '';

    public bool $editingContext = false;

    public string $titleDraft = '';

    public bool $editingTitle = false;

    public string $secondOpinionTab = 'all';

    public string $secondOpinionSourceTab = 'all';

    public string $markCommentBody = '';

    public ?int $activeCommentMarkId = null;

    public string $shareExpiryDate = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->loadReview();
    }

    public function loadReview(): void
    {
        $review = Review::query()
            ->where(fn ($q) => $q->where('token', $this->token)->orWhere('share_token', $this->token))
            ->with(['screenshots.annotations.comments', 'screenshots.annotations.afterScreenshot', 'screenshots.findings', 'parent.screenshots.annotations.comments', 'parent.screenshots.annotations.afterScreenshot'])
            ->firstOrFail();

        $this->mode = hash_equals((string) $review->token, $this->token) ? 'owner' : 'guest';
        $this->review = $review;

        if (! $this->editingContext) {
            $this->contextDraft = (string) ($review->context ?? '');
        }

        if (! $this->editingTitle) {
            $this->titleDraft = (string) $review->title;
        }

        $this->syncSecondOpinionTab();
    }

    public function isOwner(): bool
    {
        return $this->mode === 'owner';
    }

    public function showDecisionNote(): bool
    {
        return $this->review->isOpenForFeedback() && $this->isOwner();
    }

    public function showDecisionCallout(): bool
    {
        return (bool) $this->review->decision_note && ! $this->showDecisionNote();
    }

    public function showStatusCallout(): bool
    {
        return in_array($this->review->effectiveStatus(), ['changes_requested', 'approved'], true);
    }

    public function selectScreenshot(int $index): void
    {
        $this->activeScreenshotIndex = $index;
        $this->secondOpinionTab = 'all';
        $this->secondOpinionSourceTab = 'all';
        $this->cancelPin();
    }

    public function setSecondOpinionSourceTab(string $tab): void
    {
        $allowed = ['all', 'checklist', 'vision'];

        if (! in_array($tab, $allowed, true)) {
            return;
        }

        $this->secondOpinionSourceTab = $tab;
        $this->secondOpinionTab = 'all';
    }

    public function setSecondOpinionTab(string $tab): void
    {
        $allowed = ['all', Finding::SEVERITY_SUGGESTION, Finding::SEVERITY_A11Y, Finding::SEVERITY_POLISH];

        if (! in_array($tab, $allowed, true)) {
            return;
        }

        $this->secondOpinionTab = $tab;
    }

    /**
     * Fall back to All when the active source or severity tab has no open findings left.
     */
    protected function syncSecondOpinionTab(): void
    {
        $shot = $this->review->screenshots->values()->get($this->activeScreenshotIndex);
        $findings = ($shot?->findings ?? collect())
            ->filter(fn (Finding $f) => $f->isOpen() && ! $f->isGuest());

        if ($this->secondOpinionSourceTab !== 'all') {
            $hasSource = $findings->contains(fn (Finding $f) => match ($this->secondOpinionSourceTab) {
                'checklist' => $f->isChecklistSource(),
                'vision' => $f->isVisionSource(),
                default => true,
            });

            if (! $hasSource) {
                $this->secondOpinionSourceTab = 'all';
                $this->secondOpinionTab = 'all';

                return;
            }
        }

        if ($this->secondOpinionTab === 'all') {
            return;
        }

        $sourceFiltered = $this->filterFindingsBySource($findings, $this->secondOpinionSourceTab);
        $hasTab = $sourceFiltered->contains(fn (Finding $f) => $f->severity === $this->secondOpinionTab);

        if (! $hasTab) {
            $this->secondOpinionTab = 'all';
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Finding>  $findings
     * @return \Illuminate\Support\Collection<int, Finding>
     */
    protected function filterFindingsBySource($findings, string $sourceTab)
    {
        return match ($sourceTab) {
            'checklist' => $findings->filter(fn (Finding $f) => $f->isChecklistSource())->values(),
            'vision' => $findings->filter(fn (Finding $f) => $f->isVisionSource())->values(),
            default => $findings->values(),
        };
    }

    public function startPin(float $x, float $y, ?float $w = null, ?float $h = null): void
    {
        if ($this->mode === 'guest' && ! $this->review->allowsGuestAccess()) {
            return;
        }

        if (! $this->review->isOpenForFeedback()) {
            return;
        }

        $x = max(0, min(1, $x));
        $y = max(0, min(1, $y));
        $w = $w !== null ? max(0, min(1 - $x, $w)) : null;
        $h = $h !== null ? max(0, min(1 - $y, $h)) : null;

        $hasRegion = $w !== null && $h !== null && $w >= 0.01 && $h >= 0.01;

        $this->pendingX = $hasRegion ? $x + ($w / 2) : $x;
        $this->pendingY = $hasRegion ? $y + ($h / 2) : $y;
        $this->pendingW = $hasRegion ? $w : null;
        $this->pendingH = $hasRegion ? $h : null;
        $this->draftBody = '';
        $this->draftSeverity = Annotation::SEVERITY_MUST_FIX;
    }

    public function cancelPin(): void
    {
        $this->pendingX = null;
        $this->pendingY = null;
        $this->pendingW = null;
        $this->pendingH = null;
        $this->draftBody = '';
    }

    public function savePin(): void
    {
        if ($this->mode === 'guest' && ! $this->review->allowsGuestAccess()) {
            return;
        }

        if (! $this->review->isOpenForFeedback() || $this->pendingX === null || $this->pendingY === null) {
            return;
        }

        $this->draftBody = FeedbackText::sanitizeBody($this->draftBody);
        $this->guestName = FeedbackText::sanitizeName($this->guestName);

        $rules = [
            'draftBody' => FeedbackText::bodyRules(),
            'draftSeverity' => ['required', 'in:'.implode(',', Annotation::severities())],
        ];

        $messages = [
            'draftBody.required' => 'Leave a note on this spot.',
        ];

        if (! $this->isOwner()) {
            $this->draftSeverity = Annotation::SEVERITY_MUST_FIX;
            FeedbackText::throttleGuest($this->review->id);
            $rules['guestName'] = FeedbackText::nameRules();
            unset($rules['draftSeverity']);
            $messages = array_merge($messages, FeedbackText::nameMessages('guestName'), [
                'guestName.required' => 'Add your name so the owner knows who suggested this.',
            ]);
        }

        $this->validate($rules, $messages);

        $screenshot = $this->review->screenshots->values()->get($this->activeScreenshotIndex);

        if (! $screenshot) {
            return;
        }

        $hasRegion = $this->pendingW !== null && $this->pendingH !== null
            && $this->pendingW >= 0.01 && $this->pendingH >= 0.01;

        $area = $hasRegion ? [
            'x' => max(0, min(1, $this->pendingX - ($this->pendingW / 2))),
            'y' => max(0, min(1, $this->pendingY - ($this->pendingH / 2))),
            'w' => $this->pendingW,
            'h' => $this->pendingH,
        ] : null;

        if ($area) {
            $area['x'] = max(0, min(1 - $area['w'], $area['x']));
            $area['y'] = max(0, min(1 - $area['h'], $area['y']));
        }

        if ($this->isOwner()) {
            app(MarkLifecycleService::class)->createMark(
                $screenshot,
                $this->pendingX,
                $this->pendingY,
                $area,
                $this->draftSeverity,
                $this->draftBody,
            );
        } else {
            $screenshot->findings()->create([
                'source' => Finding::SOURCE_GUEST,
                'author' => $this->guestName,
                'severity' => $this->draftSeverity,
                'body' => $this->draftBody,
                'x' => $this->pendingX,
                'y' => $this->pendingY,
                'area' => $area,
                'status' => Finding::STATUS_OPEN,
            ]);
        }

        $this->cancelPin();
        $this->loadReview();
    }

    public function deletePin(int $annotationId): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $annotation = Annotation::query()
            ->whereKey($annotationId)
            ->whereHas('screenshot', fn ($q) => $q->where('review_id', $this->review->id))
            ->first();

        $annotation?->delete();
        $this->loadReview();
    }

    /**
     * Owner can verify/reopen marks while waiting on the first look and after
     * requesting changes (so the agent's resolutions can be checked next pass).
     */
    public function canManageMarks(): bool
    {
        return $this->isOwner()
            && in_array($this->review->effectiveStatus(), [Review::STATUS_PENDING, Review::STATUS_CHANGES_REQUESTED], true);
    }

    public function verifyMark(int $annotationId, MarkLifecycleService $lifecycle): void
    {
        if (! $this->canManageMarks()) {
            return;
        }

        $annotation = $this->ownedAnnotation($annotationId);

        if ($annotation) {
            $lifecycle->verify($annotation);
        }

        $this->loadReview();
    }

    public function reopenMark(int $annotationId, MarkLifecycleService $lifecycle): void
    {
        if (! $this->canManageMarks()) {
            return;
        }

        $annotation = $this->ownedAnnotation($annotationId);

        if ($annotation) {
            $lifecycle->reopen($annotation);
        }

        $this->loadReview();
    }

    /**
     * A mark reachable from this review or its parent pass (for the previous-pass panel).
     */
    protected function ownedAnnotation(int $annotationId): ?Annotation
    {
        $reviewIds = array_filter([$this->review->id, $this->review->parent_id]);

        return Annotation::query()
            ->whereKey($annotationId)
            ->whereHas('screenshot', fn ($q) => $q->whereIn('review_id', $reviewIds))
            ->first();
    }

    public function startMarkComment(int $annotationId): void
    {
        if (! $this->review->allowsComments()) {
            return;
        }

        if ($this->mode === 'guest' && ! $this->review->allowsGuestAccess()) {
            return;
        }

        if (! $this->ownedAnnotation($annotationId)) {
            return;
        }

        $this->activeCommentMarkId = $annotationId;
        $this->markCommentBody = '';
        $this->resetValidation(['markCommentBody', 'guestName']);
    }

    public function cancelMarkComment(): void
    {
        $this->activeCommentMarkId = null;
        $this->markCommentBody = '';
        $this->resetValidation(['markCommentBody', 'guestName']);
    }

    /**
     * Threaded notes on a mark. Guests must sign with a name (same as suggestions);
     * owners may leave the name blank and post as Owner.
     */
    public function addMarkComment(int $annotationId): void
    {
        if (! $this->review->allowsComments()) {
            return;
        }

        if ($this->mode === 'guest' && ! $this->review->allowsGuestAccess()) {
            return;
        }

        $annotation = $this->ownedAnnotation($annotationId);

        if (! $annotation) {
            return;
        }

        $this->markCommentBody = FeedbackText::sanitizeBody($this->markCommentBody);
        $this->guestName = FeedbackText::sanitizeName($this->guestName);

        $rules = [
            'markCommentBody' => FeedbackText::bodyRules(),
        ];

        $messages = [];

        if ($this->mode === 'guest') {
            FeedbackText::throttleGuest($this->review->id);
            $rules['guestName'] = FeedbackText::nameRules();
            $messages = FeedbackText::nameMessages('guestName');
        }

        $this->validate($rules, $messages);

        $annotation->comments()->create([
            'author' => $this->mode === 'guest' ? $this->guestName : 'Owner',
            'from_owner' => $this->mode === 'owner',
            'body' => $this->markCommentBody,
        ]);

        $this->activeCommentMarkId = null;
        $this->markCommentBody = '';
        $this->loadReview();
    }

    public function acceptFinding(int $findingId): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $finding = Finding::query()
            ->whereKey($findingId)
            ->whereHas('screenshot', fn ($q) => $q->where('review_id', $this->review->id))
            ->first();

        if (! $finding || ! $finding->isOpen()) {
            return;
        }

        $screenshot = $finding->screenshot;
        $area = is_array($finding->area) ? $finding->area : null;
        $hasRegion = is_array($area)
            && (float) ($area['w'] ?? 0) >= 0.01
            && (float) ($area['h'] ?? 0) >= 0.01;

        $x = $finding->x !== null
            ? (float) $finding->x
            : ($hasRegion ? (float) $area['x'] + ((float) $area['w'] / 2) : 0.5);
        $y = $finding->y !== null
            ? (float) $finding->y
            : ($hasRegion ? (float) $area['y'] + ((float) $area['h'] / 2) : 0.5);

        $pin = app(MarkLifecycleService::class)->createMark(
            $screenshot,
            (float) $x,
            (float) $y,
            $hasRegion ? [
                'x' => (float) $area['x'],
                'y' => (float) $area['y'],
                'w' => (float) $area['w'],
                'h' => (float) $area['h'],
            ] : null,
            $finding->pinSeverity(),
            $finding->body,
        );

        $finding->update([
            'status' => Finding::STATUS_ACCEPTED,
            'related_pin' => $pin->number,
        ]);

        $this->loadReview();
    }

    public function dismissFinding(int $findingId): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $finding = Finding::query()
            ->whereKey($findingId)
            ->whereHas('screenshot', fn ($q) => $q->where('review_id', $this->review->id))
            ->first();

        if (! $finding || ! $finding->isOpen()) {
            return;
        }

        $finding->update(['status' => Finding::STATUS_DISMISSED]);
        $this->loadReview();
    }

    public function refreshSecondOpinion(SecondOpinionService $opinions): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $opinions->requestForReview($this->review, $this->activeScreenshotIndex);
        $this->loadReview();
    }

    public function regenerateShareToken(): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $this->review->regenerateShareToken();
        $this->loadReview();

        $this->dispatch('share-url-updated', url: $this->review->shareUrl());
    }

    /**
     * @param  '7d'|'14d'|'never'|string  $preset
     */
    public function setShareExpiry(string $preset): void
    {
        if (! $this->isOwner()) {
            return;
        }

        if (! in_array($preset, ['7d', '14d', 'never'], true)) {
            return;
        }

        $expiresAt = match ($preset) {
            '7d' => now()->addDays(7)->endOfDay(),
            '14d' => now()->addDays(14)->endOfDay(),
            'never' => null,
        };

        $this->review->update(['share_expires_at' => $expiresAt]);
        $this->loadReview();
    }

    /**
     * Custom guest-link expiry from the share calendar (Y-m-d, end of that day).
     */
    public function setShareExpiryDate(string $date): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $this->shareExpiryDate = $date;

        $this->validate([
            'shareExpiryDate' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'shareExpiryDate.after_or_equal' => 'Pick today or a future date.',
        ]);

        $this->review->update([
            'share_expires_at' => \Illuminate\Support\Carbon::parse($this->shareExpiryDate)
                ->timezone(config('app.timezone'))
                ->endOfDay(),
        ]);

        $this->resetValidation('shareExpiryDate');
        $this->loadReview();
    }

    public function toggleComments(): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $this->review->update([
            'comments_enabled' => ! $this->review->allowsComments(),
        ]);
        $this->loadReview();
    }

    public function startEditContext(): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->contextDraft = (string) ($this->review->context ?? '');
        $this->editingContext = true;
    }

    public function cancelEditContext(): void
    {
        $this->editingContext = false;
        $this->contextDraft = (string) ($this->review->context ?? '');
        $this->resetValidation('contextDraft');
    }

    public function blurSaveContext(): void
    {
        if (! $this->editingContext) {
            return;
        }

        $draft = trim($this->contextDraft);
        $current = trim((string) ($this->review->context ?? ''));

        if ($draft === $current) {
            $this->cancelEditContext();

            return;
        }

        $this->saveContext();
    }

    public function saveContext(): void
    {
        if (! $this->editingContext || ! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->validate([
            'contextDraft' => ['nullable', 'string', 'max:5000'],
        ]);

        $context = trim($this->contextDraft);
        $this->review->update([
            'context' => $context !== '' ? $context : null,
        ]);

        $this->editingContext = false;
        $this->loadReview();
    }

    public function startEditTitle(): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->titleDraft = (string) $this->review->title;
        $this->editingTitle = true;
    }

    public function cancelEditTitle(): void
    {
        $this->editingTitle = false;
        $this->titleDraft = (string) $this->review->title;
        $this->resetValidation('titleDraft');
    }

    public function blurSaveTitle(): void
    {
        if (! $this->editingTitle) {
            return;
        }

        if (trim($this->titleDraft) === trim((string) $this->review->title)) {
            $this->cancelEditTitle();

            return;
        }

        $this->saveTitle();
    }

    public function saveTitle(): void
    {
        if (! $this->editingTitle || ! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->validate([
            'titleDraft' => ['required', 'string', 'max:200'],
        ], [
            'titleDraft.required' => 'Give this review a title.',
        ]);

        $this->review->update([
            'title' => trim($this->titleDraft),
        ]);

        $this->editingTitle = false;
        $this->loadReview();
    }

    public function approve(): void
    {
        $this->decide(Review::STATUS_APPROVED);
    }

    public function requestChanges(): void
    {
        $this->decide(Review::STATUS_CHANGES_REQUESTED);
    }

    protected function decide(string $status): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->validate([
            'decisionNote' => ['nullable', 'string', 'max:5000'],
        ]);

        app(ReviewService::class)->decide($this->review, $status, $this->decisionNote ?: null);

        $this->loadReview();
    }

    /**
     * Live updates over the review's public (token-keyed) channel, with the
     * polling heartbeat on the view as a fallback when Echo is unavailable.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        // Leading dot matches the exact broadcastAs() name (no namespace prefix).
        return [
            "echo:review.{$this->token},.MarkUpdated" => 'loadReview',
            "echo:review.{$this->token},.ReviewDecided" => 'loadReview',
        ];
    }

    public function getActiveScreenshotProperty()
    {
        return $this->review->screenshots->values()->get($this->activeScreenshotIndex);
    }

    public function getOpinionPendingProperty(): bool
    {
        return $this->review->screenshots->contains(
            fn (Screenshot $shot) => $shot->second_opinion_status === Screenshot::OPINION_QUEUED
        );
    }
};
?>

<div
    class="flex h-svh max-h-svh flex-col overflow-hidden"
    @if ($this->opinionPending)
        wire:poll.visible.3s="loadReview"
    @else
        wire:poll.visible.30s="loadReview"
    @endif
>
    <header class="relative z-40 shrink-0 border-b border-zinc-200/80 bg-zinc-50/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5 sm:gap-4 sm:px-6">
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1.5">
                <div class="flex shrink-0 items-center gap-3">
                    <a href="/" class="inline-flex shrink-0 items-center hover:opacity-90" aria-label="ReviseMy home">
                        <x-revisemy-logo size="sm" />
                    </a>
                    <h1 class="shrink-0 text-lg font-semibold tracking-tight text-zinc-900">Review</h1>
                </div>

                <div class="flex min-w-0 basis-full flex-wrap items-center gap-x-2 gap-y-1 sm:basis-auto sm:flex-1">
                    <span class="inline-flex shrink-0 items-center rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-zinc-600">
                        Pass {{ $review->pass }}
                    </span>
                    @php($sourceKind = $review->sourceKind())
                    @if ($sourceKind === \App\Models\Review::SOURCE_URL && $review->sourceDomain())
                        <span
                            class="inline-flex shrink-0 items-center gap-1 rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-zinc-600"
                            title="Snapshot of {{ $review->page_url }}{{ $review->capturedAt() ? ' · '.$review->capturedAt()->timezone(config('app.timezone'))->toDayDateTimeString() : '' }}"
                        >
                            <flux:icon.link variant="micro" class="size-3 text-zinc-400" />
                            <span class="max-w-40 truncate">{{ $review->sourceDomain() }}</span>
                            @if ($review->capturedAt())
                                <span class="font-normal text-zinc-400">· captured {{ $review->capturedAt()->diffForHumans() }}</span>
                            @endif
                        </span>
                    @else
                        <span class="inline-flex shrink-0 items-center rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-zinc-600">
                            {{ $review->sourceKindLabel() }}
                        </span>
                    @endif
                    @if ($review->effectiveStatus() === 'changes_requested')
                        <span class="inline-flex shrink-0 items-center rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">Changes requested</span>
                    @elseif ($review->effectiveStatus() === 'approved')
                        <span class="inline-flex shrink-0 items-center rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800">Approved</span>
                    @elseif ($review->effectiveStatus() === 'expired')
                        <span class="inline-flex shrink-0 items-center rounded-md border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-800">Expired</span>
                    @endif
                    <span class="hidden text-zinc-300 sm:inline" aria-hidden="true">·</span>
                    @if ($this->isOwner() && $review->isOpenForFeedback())
                        <div class="flex min-w-0 flex-1 items-center gap-1" wire:key="title-field">
                            @if ($editingTitle)
                                <input
                                    type="text"
                                    wire:model="titleDraft"
                                    maxlength="200"
                                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-zinc-800 outline-none ring-0 placeholder:text-zinc-400"
                                    placeholder="Review title"
                                    x-data
                                    x-init="$el.focus(); $el.select()"
                                    x-on:keydown.enter.prevent="$wire.saveTitle()"
                                    x-on:keydown.escape.prevent="$wire.cancelEditTitle()"
                                    x-on:blur="$wire.blurSaveTitle()"
                                    aria-label="Review title"
                                />
                            @else
                                <button
                                    type="button"
                                    wire:click="startEditTitle"
                                    class="min-w-0 truncate text-left text-sm text-zinc-500 transition hover:text-zinc-800"
                                    title="Click to edit title"
                                >
                                    {{ $review->title }}
                                </button>
                            @endif
                            <div class="flex w-14 shrink-0 items-center justify-end gap-0.5">
                                @if ($editingTitle)
                                    <button
                                        type="button"
                                        wire:click="saveTitle"
                                        x-on:mousedown.prevent
                                        class="inline-flex size-6 items-center justify-center rounded-md text-rose-600 transition hover:bg-rose-50"
                                        aria-label="Save title"
                                        title="Save"
                                    >
                                        <flux:icon.check variant="micro" class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="cancelEditTitle"
                                        x-on:mousedown.prevent
                                        class="inline-flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                                        aria-label="Cancel"
                                        title="Cancel"
                                    >
                                        <flux:icon.x-mark variant="micro" class="size-3.5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="min-w-0 truncate text-sm text-zinc-500" title="{{ $review->title }}">{{ $review->title }}</p>
                    @endif
                </div>
            </div>

            @if ($mode === 'guest')
                <div class="flex shrink-0 items-center">
                    @if (! $review->allowsGuestAccess())
                        <span class="rounded-md border border-rose-300 bg-rose-50 px-2 py-1 text-[11px] font-medium text-rose-800">
                            Guest link expired
                        </span>
                    @else
                        <span class="rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800">
                            Guest
                        </span>
                    @endif
                </div>
            @elseif ($this->isOwner())
                <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    <div
                        class="relative"
                        x-data="{
                            copied: false,
                            shareUrl: {{ \Illuminate\Support\Js::from($review->shareUrl()) }},
                            pickingDate: false,
                            cursor: (() => {
                                const seed = {{ \Illuminate\Support\Js::from(optional($review->share_expires_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }};
                                const [y, m] = seed.split('-').map(Number);
                                return new Date(y, m - 1, 1);
                            })(),
                            selected: {{ \Illuminate\Support\Js::from(optional($review->share_expires_at)->format('Y-m-d')) }},
                            today: {{ \Illuminate\Support\Js::from(now()->format('Y-m-d')) }},
                            weekdays: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                            async copyLink() {
                                await navigator.clipboard.writeText(this.shareUrl);
                                this.copied = true;
                                setTimeout(() => this.copied = false, 2000);
                            },
                            monthLabel() {
                                return this.cursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                            },
                            shiftMonth(delta) {
                                this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + delta, 1);
                            },
                            days() {
                                const year = this.cursor.getFullYear();
                                const month = this.cursor.getMonth();
                                const firstDow = new Date(year, month, 1).getDay();
                                const daysInMonth = new Date(year, month + 1, 0).getDate();
                                const cells = [];
                                for (let i = 0; i < firstDow; i++) cells.push(null);
                                for (let d = 1; d <= daysInMonth; d++) {
                                    const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                                    cells.push({ day: d, iso, disabled: iso < this.today });
                                }
                                while (cells.length % 7 !== 0) cells.push(null);
                                return cells;
                            },
                            pick(iso) {
                                if (! iso) return;
                                this.selected = iso;
                                $wire.setShareExpiryDate(iso).then(() => {
                                    this.pickingDate = false;
                                });
                            }
                        }"
                        x-on:share-url-updated.window="shareUrl = $event.detail.url"
                        x-on:keydown.escape.window="pickingDate = false"
                    >
                        <flux:dropdown position="bottom" align="end">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="link"
                                icon:trailing="chevron-down"
                                class="!bg-zinc-100 hover:!bg-zinc-200/80"
                            >
                                <span x-show="! copied">Share</span>
                                <span x-show="copied" x-cloak>Copied!</span>
                            </flux:button>

                            <flux:menu class="min-w-60">
                                <flux:menu.item icon="clipboard-document" x-on:click="copyLink()">
                                    Copy guest link
                                </flux:menu.item>
                                <flux:menu.item
                                    icon="arrow-path"
                                    wire:click="regenerateShareToken"
                                    wire:confirm="Regenerate the guest link? Anyone with the old link loses access."
                                >
                                    Generate new link
                                </flux:menu.item>
                                <flux:menu.separator />
                                <div class="px-3 py-1.5 text-[11px] font-medium uppercase tracking-wide text-zinc-400">Guest link expires</div>
                                <flux:menu.item wire:click="setShareExpiry('7d')">
                                    In 7 days
                                    <span class="ms-auto text-[10px] font-medium text-zinc-400">Default</span>
                                </flux:menu.item>
                                <flux:menu.item wire:click="setShareExpiry('14d')">In 14 days</flux:menu.item>
                                <flux:menu.item wire:click="setShareExpiry('never')">Never</flux:menu.item>
                                <flux:menu.item icon="calendar-days" x-on:click="pickingDate = true">
                                    Custom date…
                                </flux:menu.item>
                                @if ($review->share_expires_at)
                                    <p class="px-3 py-1.5 text-[11px] leading-snug text-zinc-500">
                                        @if ($review->isShareLinkExpired())
                                            Expired {{ $review->share_expires_at->diffForHumans() }}
                                        @else
                                            Expires {{ $review->share_expires_at->timezone(config('app.timezone'))->toFormattedDateString() }}
                                            · {{ $review->share_expires_at->diffForHumans() }}
                                        @endif
                                    </p>
                                @endif
                                <flux:menu.separator />
                                <flux:menu.item
                                    icon="{{ $review->allowsComments() ? 'chat-bubble-left-right' : 'no-symbol' }}"
                                    wire:click="toggleComments"
                                >
                                    {{ $review->allowsComments() ? 'Disable comments' : 'Enable comments' }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        <div
                            x-show="pickingDate"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                            class="absolute right-0 top-[calc(100%+0.5rem)] z-[60] w-[17.5rem] overflow-hidden rounded-2xl border border-zinc-200 bg-white p-3 shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]"
                            x-on:click.outside="pickingDate = false"
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800"
                                    x-on:click="shiftMonth(-1)"
                                    aria-label="Previous month"
                                >
                                    <flux:icon.chevron-left variant="micro" class="size-4" />
                                </button>
                                <p class="text-sm font-medium text-zinc-800" x-text="monthLabel()"></p>
                                <button
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800"
                                    x-on:click="shiftMonth(1)"
                                    aria-label="Next month"
                                >
                                    <flux:icon.chevron-right variant="micro" class="size-4" />
                                </button>
                            </div>

                            <div class="mb-1 grid grid-cols-7 gap-0.5">
                                <template x-for="label in weekdays" :key="label">
                                    <div class="py-1 text-center text-[10px] font-medium uppercase tracking-wide text-zinc-400" x-text="label"></div>
                                </template>
                            </div>

                            <div class="grid grid-cols-7 gap-0.5">
                                <template x-for="(cell, index) in days()" :key="index">
                                    <div class="aspect-square">
                                        <button
                                            type="button"
                                            x-show="cell"
                                            x-bind:disabled="cell?.disabled"
                                            x-on:click="pick(cell.iso)"
                                            class="flex size-full items-center justify-center rounded-full text-sm transition disabled:cursor-not-allowed disabled:opacity-30"
                                            x-bind:class="cell && cell.iso === selected
                                                ? 'bg-rose-500 font-semibold text-white shadow-sm'
                                                : (cell && cell.iso === today
                                                    ? 'font-semibold text-rose-600 ring-1 ring-inset ring-rose-200 hover:bg-rose-50'
                                                    : 'text-zinc-700 hover:bg-zinc-100')"
                                            x-text="cell?.day"
                                        ></button>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-2 border-t border-zinc-100 pt-3">
                                <button
                                    type="button"
                                    class="text-xs font-medium text-zinc-500 transition hover:text-zinc-800"
                                    x-on:click="pickingDate = false"
                                >Cancel</button>
                                <button
                                    type="button"
                                    class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200/80"
                                    x-on:click="pick(today)"
                                >Today</button>
                            </div>
                            <flux:error name="shareExpiryDate" />
                        </div>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="view-columns" href="{{ $review->boardUrl() }}" class="!bg-zinc-100 hover:!bg-zinc-200/80">Board</flux:button>
                    @if ($review->isOpenForFeedback())
                        <div class="hidden items-center gap-2 lg:flex">
                            <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="requestChanges" wire:confirm="Request changes and send marks back to the agent?" class="!bg-zinc-100 hover:!bg-zinc-200/80">Changes</flux:button>
                            <flux:button size="sm" variant="primary" icon="check" wire:click="approve" wire:confirm="Approve this pass? Resolved marks will be verified and the loop closes." class="!bg-rose-600 hover:!bg-rose-700">Approve</flux:button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </header>

    @if ($mode === 'guest' && ! $review->allowsGuestAccess())
        <div class="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center px-4 py-16 sm:px-6">
            <flux:callout variant="danger" icon="lock-closed">
                <flux:callout.heading>This guest link has expired</flux:callout.heading>
                <flux:callout.text>
                    Ask the owner for a new share link if you still need to leave suggestions or comments.
                </flux:callout.text>
            </flux:callout>
        </div>
    @else
    <div
        class="mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden px-4 sm:px-6 xl:grid xl:grid-cols-[minmax(0,1fr)_20rem] xl:gap-x-6 xl:overflow-hidden"
        x-data
        x-init="
            if (! Alpine.store('rmFocus')) {
                Alpine.store('rmFocus', { finding: null, mark: null });
            }
        "
    >
        @php($suggestionNumbers = $review->suggestionDisplayNumbers())
        <section class="min-w-0 shrink-0 max-h-[52svh] space-y-3 overflow-y-auto pt-4 sm:space-y-4 sm:pt-5 xl:max-h-none xl:min-h-0 xl:overflow-y-auto xl:pt-6">
            @if ($review->context || ($this->isOwner() && $review->isOpenForFeedback()))
                <div class="grid gap-1.5 sm:grid-cols-[9.5rem_1fr] sm:gap-4">
                    <flux:heading size="sm" class="sm:pt-0.5">What to look at</flux:heading>

                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            @if ($editingContext && $this->isOwner() && $review->isOpenForFeedback())
                                <textarea
                                    wire:key="context-editor"
                                    wire:model="contextDraft"
                                    rows="3"
                                    maxlength="5000"
                                    placeholder="What should they look at on this pass?"
                                    class="w-full resize-y border-0 bg-transparent p-0 text-sm leading-relaxed text-pretty text-zinc-600 outline-none ring-0 placeholder:text-zinc-400 sm:text-base"
                                    x-data
                                    x-init="$el.focus()"
                                    x-on:keydown.meta.enter.prevent="$wire.saveContext()"
                                    x-on:keydown.ctrl.enter.prevent="$wire.saveContext()"
                                    x-on:keydown.escape.prevent="$wire.cancelEditContext()"
                                    x-on:blur="$wire.blurSaveContext()"
                                ></textarea>
                            @elseif ($this->isOwner() && $review->isOpenForFeedback())
                                <button
                                    type="button"
                                    wire:click="startEditContext"
                                    class="w-full text-left transition hover:text-zinc-800"
                                    title="Click to edit"
                                >
                                    @if ($review->context)
                                        <p class="text-sm leading-relaxed text-pretty text-zinc-600 sm:text-base">
                                            {{ $review->context }}
                                        </p>
                                    @else
                                        <p class="text-sm text-zinc-400 sm:text-base">
                                            Add what to look at on this pass…
                                        </p>
                                    @endif
                                </button>
                            @elseif ($review->context)
                                <p class="text-sm leading-relaxed text-pretty text-zinc-600 sm:text-base">
                                    {{ $review->context }}
                                </p>
                            @endif
                        </div>

                        @if ($this->isOwner() && $review->isOpenForFeedback())
                            <div class="flex w-14 shrink-0 items-center justify-end gap-0.5 pt-0.5">
                                @if ($editingContext)
                                    <button
                                        type="button"
                                        wire:click="saveContext"
                                        x-on:mousedown.prevent
                                        class="inline-flex size-6 items-center justify-center rounded-md text-rose-600 transition hover:bg-rose-50"
                                        aria-label="Save"
                                        title="Save · ⌘Enter"
                                    >
                                        <flux:icon.check variant="micro" class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="cancelEditContext"
                                        x-on:mousedown.prevent
                                        class="inline-flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                                        aria-label="Cancel"
                                        title="Cancel · Esc"
                                    >
                                        <flux:icon.x-mark variant="micro" class="size-3.5" />
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @php($shot = $this->activeScreenshot)

            <div @class(['sm:flex sm:items-start sm:gap-3' => $review->screenshots->count() > 1])>

            @if ($review->screenshots->count() > 1)
                <div
                    class="mb-2 flex gap-2 overflow-x-auto p-1 sm:mb-0 sm:max-h-[min(70svh,560px)] sm:w-24 sm:shrink-0 sm:flex-col sm:overflow-y-auto sm:overflow-x-visible"
                    role="tablist"
                    aria-label="Screenshots"
                    aria-orientation="vertical"
                >
                    @foreach ($review->screenshots as $index => $shotOption)
                        <button
                            type="button"
                            role="tab"
                            wire:key="rail-{{ $shotOption->id }}"
                            wire:click="selectScreenshot({{ $index }})"
                            x-on:click="$store.rmFocus && ($store.rmFocus.finding = null, $store.rmFocus.mark = null)"
                            aria-label="{{ $shotOption->railLabel($index) }}"
                            aria-selected="{{ $activeScreenshotIndex === $index ? 'true' : 'false' }}"
                            title="{{ $shotOption->railLabel($index) }}"
                            @class([
                                'group relative w-16 shrink-0 overflow-hidden rounded-xl transition duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 active:scale-[0.97] sm:w-full',
                                'shadow-sm ring-2 ring-rose-500 ring-offset-2' => $activeScreenshotIndex === $index,
                                'opacity-80 ring-1 ring-zinc-200 hover:opacity-100 hover:ring-zinc-300' => $activeScreenshotIndex !== $index,
                            ])
                        >
                            <img
                                src="{{ $shotOption->thumbUrl() }}"
                                alt=""
                                loading="lazy"
                                draggable="false"
                                class="pointer-events-none block aspect-[4/5] w-full bg-zinc-100 object-cover object-top"
                            />
                            <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-zinc-900/70 to-transparent px-1.5 pb-1 pt-4 text-left text-[10px] font-medium text-white">
                                {{ $shotOption->railLabel($index) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="min-w-0 flex-1 space-y-3 sm:space-y-4">

            @if ($shot)
                <div
                    wire:key="shot-viewer-{{ $activeScreenshotIndex }}"
                    class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-sm"
                    x-data="{
                        zoom: 1,
                        zoomMin: 1,
                        zoomMax: 3,
                        zoomStep: 0.25,
                        naturalWidth: {{ (int) ($shot->width ?: 0) }},
                        naturalHeight: {{ (int) ($shot->height ?: 0) }},
                        baseWidth: 0,
                        drawing: false,
                        panning: false,
                        spaceHeld: false,
                        panStartX: 0,
                        panStartY: 0,
                        panScrollLeft: 0,
                        panScrollTop: 0,
                        startX: 0,
                        startY: 0,
                        draft: null,
                        resizeObserver: null,
                        init() {
                            this.$nextTick(() => {
                                this.measureLayout();
                                const img = this.$refs.shotImg;
                                if (img?.complete && img.naturalWidth) {
                                    this.naturalWidth = img.naturalWidth;
                                    this.naturalHeight = img.naturalHeight;
                                    this.measureLayout();
                                }
                                this.resizeObserver = new ResizeObserver(() => this.measureLayout());
                                if (this.$refs.viewport) {
                                    this.resizeObserver.observe(this.$refs.viewport);
                                }
                            });
                        },
                        destroy() {
                            this.resizeObserver?.disconnect();
                        },
                        onImageLoad() {
                            const img = this.$refs.shotImg;
                            if (! img) return;
                            this.naturalWidth = img.naturalWidth;
                            this.naturalHeight = img.naturalHeight;
                            this.measureLayout();
                        },
                        measureLayout() {
                            const vp = this.$refs.viewport;
                            if (! vp) return;
                            const containerWidth = vp.clientWidth;
                            const natural = this.naturalWidth || containerWidth;
                            this.baseWidth = natural > 0 ? Math.min(containerWidth, natural) : containerWidth;
                        },
                        canvasStyle() {
                            const w = Math.max(1, Math.round(this.baseWidth * this.zoom));

                            return 'width: ' + w + 'px';
                        },
                        viewportCanScroll() {
                            const vp = this.$refs.viewport;
                            if (! vp) return false;

                            return vp.scrollHeight > vp.clientHeight + 1 || vp.scrollWidth > vp.clientWidth + 1;
                        },
                        canPan() {
                            return this.viewportCanScroll();
                        },
                        zoomIn() {
                            this.zoom = Math.min(this.zoomMax, +(this.zoom + this.zoomStep).toFixed(2));
                        },
                        zoomOut() {
                            this.zoom = Math.max(this.zoomMin, +(this.zoom - this.zoomStep).toFixed(2));
                        },
                        resetZoom() {
                            this.zoom = 1;
                            this.$refs.viewport?.scrollTo({ top: 0, left: 0, behavior: 'instant' });
                        },
                        onKeyDown(e) {
                            if (e.code !== 'Space') return;
                            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable) return;
                            this.spaceHeld = true;
                            if (this.canPan()) e.preventDefault();
                        },
                        onKeyUp(e) {
                            if (e.code !== 'Space') return;
                            this.spaceHeld = false;
                            this.panning = false;
                        },
                        isPanMode(e) {
                            return e.button === 1 || (this.spaceHeld && e.button === 0 && this.canPan());
                        },
                        beginPan(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const vp = this.$refs.viewport;
                            if (! vp) return;
                            this.panning = true;
                            this.panStartX = e.clientX;
                            this.panStartY = e.clientY;
                            this.panScrollLeft = vp.scrollLeft;
                            this.panScrollTop = vp.scrollTop;
                        },
                        movePan(e) {
                            if (! this.panning) return;
                            const vp = this.$refs.viewport;
                            if (! vp) return;
                            vp.scrollLeft = this.panScrollLeft - (e.clientX - this.panStartX);
                            vp.scrollTop = this.panScrollTop - (e.clientY - this.panStartY);
                        },
                        endPan() {
                            this.panning = false;
                        },
                        scrollParentEl() {
                            let node = this.$refs.viewport;
                            while (node) {
                                node = node.parentElement;
                                if (! node) {
                                    return null;
                                }
                                const { overflowY } = getComputedStyle(node);
                                if (/(auto|scroll)/.test(overflowY) && node.scrollHeight > node.clientHeight + 1) {
                                    return node;
                                }
                            }
                            return null;
                        },
                        onWheel(e) {
                            if (this.viewportCanScroll()) {
                                return;
                            }

                            const parent = this.scrollParentEl();
                            if (! parent) return;

                            parent.scrollTop += e.deltaY;
                            e.preventDefault();
                        },
                        norm(e) {
                            const canvas = this.$refs.canvas;
                            if (! canvas) return null;
                            const rect = canvas.getBoundingClientRect();
                            return {
                                x: Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)),
                                y: Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height)),
                            };
                        },
                        onMouseMove(e) {
                            if (this.panning) {
                                this.movePan(e);
                                return;
                            }
                            this.moveDraw(e);
                        },
                        onMouseUp(e) {
                            if (this.panning) {
                                this.endPan();
                                return;
                            }
                            this.endDraw(e);
                        },
                        beginDraw(e) {
                            if (this.isPanMode(e)) {
                                this.beginPan(e);
                                return;
                            }
                            if (e.button !== 0) return;
                            if (e.target.closest('[data-finding], [data-pin], [data-zoom-controls]')) return;
                            const p = this.norm(e);
                            if (! p) return;
                            this.drawing = true;
                            this.startX = p.x;
                            this.startY = p.y;
                            this.draft = { x: p.x, y: p.y, w: 0, h: 0 };
                            e.preventDefault();
                        },
                        moveDraw(e) {
                            if (! this.drawing) return;
                            const p = this.norm(e);
                            if (! p) return;
                            const x = Math.min(this.startX, p.x);
                            const y = Math.min(this.startY, p.y);
                            this.draft = {
                                x,
                                y,
                                w: Math.abs(p.x - this.startX),
                                h: Math.abs(p.y - this.startY),
                            };
                        },
                        endDraw(e) {
                            if (! this.drawing) return;
                            this.drawing = false;
                            const draft = this.draft;
                            this.draft = null;
                            if (! draft) return;
                            if (draft.w >= 0.01 && draft.h >= 0.01) {
                                $wire.startPin(draft.x, draft.y, draft.w, draft.h);
                            } else {
                                $wire.startPin(this.startX, this.startY);
                            }
                        },
                        cancelDraw() {
                            this.drawing = false;
                            this.draft = null;
                            this.panning = false;
                        }
                    }"
                    x-init="init(); return () => destroy()"
                    x-on:keydown.window="onKeyDown($event)"
                    x-on:keyup.window="onKeyUp($event)"
                >
                    <div data-zoom-controls class="absolute left-2 top-2 z-30 flex items-center gap-0.5 rounded-lg border border-zinc-200/80 bg-white/95 p-0.5 shadow-sm backdrop-blur">
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
                            x-on:click="zoomOut()"
                            x-bind:disabled="zoom <= zoomMin"
                            aria-label="Zoom out"
                        >−</button>
                        <button
                            type="button"
                            class="min-w-[2.75rem] rounded-md px-1.5 py-1 font-mono text-[10px] text-zinc-500 transition hover:bg-zinc-100"
                            x-on:click="resetZoom()"
                            x-text="Math.round(zoom * 100) + '%'"
                            aria-label="Reset zoom"
                        ></button>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md text-sm text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
                            x-on:click="zoomIn()"
                            x-bind:disabled="zoom >= zoomMax"
                            aria-label="Zoom in"
                        >+</button>
                    </div>

                    <div
                        x-ref="viewport"
                        class="max-h-[min(52svh,420px)] overflow-auto overscroll-contain sm:max-h-[min(65svh,520px)] lg:max-h-[min(70svh,560px)] flex justify-center"
                        x-bind:class="{
                            'cursor-grab': canPan() && spaceHeld && !panning,
                            'cursor-grabbing': panning
                        }"
                        x-on:wheel="onWheel($event)"
                        x-on:mousedown="isPanMode($event) && beginPan($event)"
                        x-on:mousemove.window="onMouseMove($event)"
                        x-on:mouseup.window="onMouseUp($event)"
                    >
                        <div
                            x-ref="canvas"
                            class="relative shrink-0 select-none"
                            x-bind:style="canvasStyle()"
                            @if ($review->isOpenForFeedback())
                                x-bind:class="!spaceHeld ? 'cursor-crosshair' : ''"
                                x-on:mousedown="beginDraw($event)"
                                x-on:keydown.escape.window="cancelDraw()"
                            @endif
                        >
                            <img
                                x-ref="shotImg"
                                src="{{ $shot->url() }}"
                                alt="Screenshot {{ $activeScreenshotIndex + 1 }}"
                                @if ($shot->width) width="{{ $shot->width }}" @endif
                                @if ($shot->height) height="{{ $shot->height }}" @endif
                                class="pointer-events-none block h-auto w-full"
                                draggable="false"
                                x-on:load="onImageLoad()"
                            />

                            @php($openFindings = $shot->findings->filter(fn ($f) => $f->isOpen() && ! $f->isGuest())->values())
                            @php($guestFindings = $shot->findings->filter(fn ($f) => $f->isOpen() && $f->isGuest())->values())
                            @php($textOnlyGuest = $guestFindings->filter(function ($f) {
                                $area = is_array($f->area) ? $f->area : null;
                                $hasRegion = $area && (float) ($area['w'] ?? 0) >= 0.01 && (float) ($area['h'] ?? 0) >= 0.01;

                                return ! $hasRegion && ($f->x === null || $f->y === null);
                            })->values())
                            @foreach ($openFindings as $findingIndex => $finding)
                                @php($area = is_array($finding->area) ? $finding->area : null)
                                @php($hasRegion = $area && (float) ($area['w'] ?? 0) >= 0.01 && (float) ($area['h'] ?? 0) >= 0.01)
                                @if ($hasRegion)
                                    @php($badgePosition = ($area['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($area['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <div
                                        data-finding
                                        class="absolute z-[5]"
                                        style="left: {{ $area['x'] * 100 }}%; top: {{ $area['y'] * 100 }}%; width: {{ $area['w'] * 100 }}%; height: {{ $area['h'] * 100 }}%;"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-sky-400/80 bg-sky-400/10 transition"
                                            title="{{ $finding->body }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-sky-500 bg-sky-400/20 ring-2 ring-sky-400/40' : ''"
                                        ></div>
                                        <button
                                            type="button"
                                            class="absolute {{ $badgePosition }} z-[6] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-0.5 text-[10px] font-semibold text-sky-700 shadow-sm transition"
                                            title="{{ $finding->body }}"
                                            x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-sky-600 bg-sky-50 ring-2 ring-sky-300' : ''"
                                        >
                                            S{{ $suggestionNumbers['s'][$finding->id] ?? ($findingIndex + 1) }}
                                        </button>
                                    </div>
                                @endif
                            @endforeach

                            @foreach ($guestFindings as $guestIndex => $finding)
                                @php($area = is_array($finding->area) ? $finding->area : null)
                                @php($hasRegion = $area && (float) ($area['w'] ?? 0) >= 0.01 && (float) ($area['h'] ?? 0) >= 0.01)
                                @if ($hasRegion)
                                    @php($badgePosition = ($area['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($area['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <div
                                        data-finding
                                        class="absolute z-[7]"
                                        style="left: {{ $area['x'] * 100 }}%; top: {{ $area['y'] * 100 }}%; width: {{ $area['w'] * 100 }}%; height: {{ $area['h'] * 100 }}%;"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border border-dashed border-amber-400/80 bg-amber-400/10 transition"
                                            title="{{ $finding->body }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'border-amber-500 bg-amber-400/20 ring-2 ring-amber-400/40' : ''"
                                        ></div>
                                        <button
                                            type="button"
                                            class="absolute {{ $badgePosition }} z-[8] flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-white px-0.5 text-[10px] font-semibold text-amber-700 shadow-sm transition"
                                            title="{{ $finding->body }}"
                                            x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-amber-600 bg-amber-50 ring-2 ring-amber-300' : ''"
                                        >
                                            G{{ $suggestionNumbers['g'][$finding->id] ?? ($guestIndex + 1) }}
                                        </button>
                                    </div>
                                @elseif ($finding->x !== null && $finding->y !== null)
                                    <button
                                        type="button"
                                        data-finding
                                        class="absolute z-[7] flex h-6 min-w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-white px-0.5 text-[10px] font-semibold text-amber-700 shadow-sm transition"
                                        style="left: {{ $finding->x * 100 }}%; top: {{ $finding->y * 100 }}%;"
                                        title="{{ $finding->body }}"
                                        x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                        x-on:click.stop="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                        x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-amber-600 bg-amber-50 ring-2 ring-amber-300' : ''"
                                    >
                                        G{{ $suggestionNumbers['g'][$finding->id] ?? ($guestIndex + 1) }}
                                    </button>
                                @endif
                            @endforeach

                            @if ($textOnlyGuest->isNotEmpty())
                                <div
                                    class="pointer-events-none absolute right-2 top-2 z-[12] flex max-w-[min(100%,12rem)] flex-col items-end gap-1"
                                    aria-label="Guest text hints on capture"
                                >
                                    @foreach ($textOnlyGuest as $finding)
                                        <button
                                            type="button"
                                            data-finding
                                            class="pointer-events-auto flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-white px-0.5 text-[10px] font-semibold text-amber-700 shadow-sm transition"
                                            title="{{ $finding->body }}"
                                            x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                            x-on:click.stop="
                                                $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                                $store.rmFocus.mark = null;
                                                document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                            "
                                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-amber-600 bg-amber-50 ring-2 ring-amber-300' : ''"
                                        >
                                            G{{ $suggestionNumbers['g'][$finding->id] ?? ($loop->iteration) }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @foreach ($shot->annotations as $annotation)
                                @php($region = $annotation->region())
                                @php($markState = $annotation->status === \App\Models\Annotation::STATUS_VERIFIED ? 'opacity-40' : ($annotation->status === \App\Models\Annotation::STATUS_RESOLVED ? 'opacity-70' : ''))
                                @if ($region)
                                    @php($markBadgePosition = ($region['y'] ?? 0) < 0.07 ? '-left-2 -bottom-2' : (($region['x'] ?? 0) < 0.07 ? '-right-2 -top-2' : '-left-2 -top-2'))
                                    <button
                                        type="button"
                                        data-pin
                                        class="absolute z-[8] block cursor-pointer border-0 bg-transparent p-0 text-left {{ $markState }}"
                                        style="left: {{ $region['x'] * 100 }}%; top: {{ $region['y'] * 100 }}%; width: {{ $region['w'] * 100 }}%; height: {{ $region['h'] * 100 }}%;"
                                        title="{{ $annotation->body }}"
                                        x-on:click.stop="
                                            $store.rmFocus.mark = $store.rmFocus.mark === {{ $annotation->id }} ? null : {{ $annotation->id }};
                                            $store.rmFocus.finding = null;
                                            document.getElementById('fb-mark-{{ $annotation->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                        "
                                    >
                                        <div
                                            class="pointer-events-none absolute inset-0 rounded-md border-2 border-rose-500/80 bg-rose-500/10 transition"
                                            x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'ring-2 ring-rose-400 ring-offset-1' : ''"
                                        ></div>
                                        <span
                                            class="pointer-events-none absolute {{ $markBadgePosition }} z-[9] flex h-6 min-w-6 items-center justify-center rounded-full px-0.5 text-[10px] font-semibold text-white shadow-sm ring-2 ring-white transition {{ $annotation->markerClass() }}"
                                            x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'scale-110' : ''"
                                        >
                                            M{{ $annotation->number }}
                                        </span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        data-pin
                                        class="absolute z-10 flex h-7 min-w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white shadow-lg ring-2 ring-white transition {{ $annotation->markerClass() }} {{ $markState }}"
                                        style="left: {{ $annotation->x * 100 }}%; top: {{ $annotation->y * 100 }}%;"
                                        title="{{ $annotation->body }}"
                                        x-bind:class="$store.rmFocus?.mark === {{ $annotation->id }} ? 'scale-110 ring-4 ring-rose-300' : ''"
                                    >
                                        M{{ $annotation->number }}
                                    </button>
                                @endif
                            @endforeach

                            <template x-if="draft && draft.w > 0 && draft.h > 0">
                                <div
                                    class="pointer-events-none absolute z-[15] rounded-md border-2 border-dashed {{ $mode === 'guest' ? 'border-amber-500 bg-amber-400/15' : 'border-rose-500 bg-rose-500/15' }}"
                                    x-bind:style="'left:' + (draft.x * 100) + '%;top:' + (draft.y * 100) + '%;width:' + (draft.w * 100) + '%;height:' + (draft.h * 100) + '%'"
                                ></div>
                            </template>

                            @if ($pendingX !== null && $pendingY !== null)
                                @if ($pendingW !== null && $pendingH !== null && $pendingW >= 0.01 && $pendingH >= 0.01)
                                    <div
                                        data-pending-mark
                                        class="pointer-events-none absolute z-[18] rounded-md border-2 border-dashed {{ $mode === 'guest' ? 'border-amber-500 bg-amber-400/15' : 'border-rose-500 bg-rose-500/15' }}"
                                        style="left: {{ ($pendingX - $pendingW / 2) * 100 }}%; top: {{ ($pendingY - $pendingH / 2) * 100 }}%; width: {{ $pendingW * 100 }}%; height: {{ $pendingH * 100 }}%;"
                                    ></div>
                                @else
                                    <div
                                        data-pending-mark
                                        class="pointer-events-none absolute z-[18] h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full {{ $mode === 'guest' ? 'bg-amber-500' : 'bg-rose-500' }}"
                                        style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                                    ></div>
                                @endif
                                <div
                                    class="absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full text-xs font-semibold shadow-lg ring-2 ring-white {{ $mode === 'guest' ? 'border-2 border-dashed border-amber-500 bg-white text-amber-700' : 'bg-rose-500 text-white' }}"
                                    style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                                >
                                    {{ $mode === 'guest' ? 'G' : '+' }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($review->isOpenForFeedback())
                    <div class="rounded-xl border border-zinc-200/80 bg-zinc-50/90 px-3 py-2.5 sm:px-4">
                        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-[11px] text-zinc-600 sm:gap-x-4 sm:text-xs">
                            <span class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border-2 border-rose-500/80 bg-rose-500/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 text-[8px] font-semibold text-white ring-2 ring-white">M</span>
                                </span>
                                Your marks
                            </span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 sm:block" aria-hidden="true"></span>
                            @if ($mode === 'owner')
                            <span class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-sky-400/80 bg-sky-400/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-sky-500 bg-white text-[8px] font-semibold text-sky-700 ring-2 ring-white">S</span>
                                </span>
                                Second opinion
                            </span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 sm:block" aria-hidden="true"></span>
                            @endif
                            <span class="inline-flex items-center gap-2">
                                <span class="relative h-4 w-7 shrink-0 rounded border border-dashed border-amber-400/80 bg-amber-400/10" aria-hidden="true">
                                    <span class="absolute -left-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-dashed border-amber-500 bg-white text-[8px] font-semibold text-amber-700 ring-2 ring-white">G</span>
                                </span>
                                Guest
                            </span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 md:block" aria-hidden="true"></span>
                            <span class="w-full text-center text-zinc-500 sm:w-auto">{{ $mode === 'guest' ? 'Drag to suggest · click for point' : 'Drag to mark · click for point' }}</span>
                            <span class="hidden h-3 w-px shrink-0 bg-zinc-300 md:block" aria-hidden="true"></span>
                            <span class="hidden text-zinc-500 sm:inline">+/− zoom · Space+drag to pan</span>
                        </div>
                    </div>
                @endif
            @else
                <flux:callout variant="warning">No screenshots on this review yet.</flux:callout>
            @endif

            </div>
            </div>
        </section>

        @php($stripMarks = $shot?->annotations ?? collect())
        @php($stripSecondOpinion = ($shot?->findings ?? collect())->filter(function ($f) {
            if (! $f->isOpen() || $f->isGuest()) {
                return false;
            }
            $area = is_array($f->area) ? $f->area : null;

            return $area && (float) ($area['w'] ?? 0) >= 0.01 && (float) ($area['h'] ?? 0) >= 0.01;
        })->values())
        @php($stripGuest = ($shot?->findings ?? collect())->filter(function ($f) {
            if (! $f->isOpen() || ! $f->isGuest()) {
                return false;
            }
            $area = is_array($f->area) ? $f->area : null;
            $hasRegion = $area && (float) ($area['w'] ?? 0) >= 0.01 && (float) ($area['h'] ?? 0) >= 0.01;

            return $hasRegion || ($f->x !== null && $f->y !== null);
        })->values())

        @if ($shot && ($stripMarks->isNotEmpty() || $stripSecondOpinion->isNotEmpty() || $stripGuest->isNotEmpty()))
            <div class="shrink-0 overflow-x-auto overscroll-x-contain border-y border-zinc-200 bg-white/95 px-3 py-2 [scrollbar-width:none] xl:hidden [&::-webkit-scrollbar]:hidden">
                <div class="flex w-max items-center gap-1.5">
                    @foreach ($stripMarks as $pin)
                        <button
                            type="button"
                            class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-semibold text-white shadow-sm ring-2 ring-white transition {{ $pin->markerClass() }}"
                            x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} ? 'scale-110 ring-rose-300' : ''"
                            x-on:click="
                                $store.rmFocus.mark = $store.rmFocus.mark === {{ $pin->id }} ? null : {{ $pin->id }};
                                $store.rmFocus.finding = null;
                                document.getElementById('fb-mark-{{ $pin->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            "
                            aria-label="Jump to mark M{{ $pin->number }}"
                        >
                            M{{ $pin->number }}
                        </button>
                    @endforeach

                    @if ($stripMarks->isNotEmpty() && ($stripSecondOpinion->isNotEmpty() || $stripGuest->isNotEmpty()))
                        <span class="h-4 w-px shrink-0 bg-zinc-200" aria-hidden="true"></span>
                    @endif

                    @foreach ($stripSecondOpinion as $finding)
                        <button
                            type="button"
                            class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-sky-500 bg-white px-1.5 text-[10px] font-semibold text-sky-700 shadow-sm transition"
                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-sky-600 bg-sky-50 ring-2 ring-sky-300' : ''"
                            x-on:click="
                                $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                $store.rmFocus.mark = null;
                                @if ($secondOpinionSourceTab !== 'all' && (
                                    ($secondOpinionSourceTab === 'checklist' && ! $finding->isChecklistSource())
                                    || ($secondOpinionSourceTab === 'vision' && ! $finding->isVisionSource())
                                ))
                                    $wire.setSecondOpinionSourceTab('all').then(() => document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                                @elseif ($secondOpinionTab !== 'all' && $secondOpinionTab !== $finding->severity)
                                    $wire.setSecondOpinionTab('all').then(() => document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                                @else
                                    document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                @endif
                            "
                            aria-label="Jump to second opinion S{{ $suggestionNumbers['s'][$finding->id] ?? '' }}"
                        >
                            S{{ $suggestionNumbers['s'][$finding->id] ?? '' }}
                        </button>
                    @endforeach

                    @if ($stripSecondOpinion->isNotEmpty() && $stripGuest->isNotEmpty())
                        <span class="h-4 w-px shrink-0 bg-zinc-200" aria-hidden="true"></span>
                    @endif

                    @foreach ($stripGuest as $finding)
                        <button
                            type="button"
                            class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-white px-1.5 text-[10px] font-semibold text-amber-700 shadow-sm transition"
                            x-bind:class="$store.rmFocus?.finding === {{ $finding->id }} ? 'scale-110 border-amber-600 bg-amber-50 ring-2 ring-amber-300' : ''"
                            x-on:click="
                                $store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }};
                                $store.rmFocus.mark = null;
                                document.getElementById('fb-finding-{{ $finding->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            "
                            aria-label="Jump to guest suggestion G{{ $suggestionNumbers['g'][$finding->id] ?? '' }}"
                        >
                            G{{ $suggestionNumbers['g'][$finding->id] ?? '' }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($pendingX !== null)
            <div
                wire:key="pending-note-{{ $pendingX }}-{{ $pendingY }}-{{ $pendingW }}-{{ $pendingH }}"
                class="contents"
                x-data="{
                    place() {
                        const panel = this.$refs.panel;
                        const mark = document.querySelector('[data-pending-mark]');
                        if (! panel) return;

                        if (window.matchMedia('(max-width: 767px)').matches) {
                            panel.style.left = '';
                            panel.style.top = '';
                            panel.style.right = '';
                            panel.style.transform = '';
                            panel.style.transformOrigin = 'bottom center';
                            return;
                        }

                        if (! mark) return;

                        const gap = 12;
                        const rect = mark.getBoundingClientRect();
                        const pw = panel.offsetWidth || 320;
                        const ph = panel.offsetHeight || 280;
                        const spaceRight = window.innerWidth - rect.right;
                        const placeRight = spaceRight >= pw + gap || spaceRight >= rect.left;
                        let left = placeRight ? rect.right + gap : rect.left - pw - gap;
                        let top = rect.top + (rect.height / 2) - (ph / 2);
                        left = Math.max(8, Math.min(left, window.innerWidth - pw - 8));
                        top = Math.max(8, Math.min(top, window.innerHeight - ph - 8));
                        panel.style.left = left + 'px';
                        panel.style.top = top + 'px';
                        panel.style.transformOrigin = placeRight ? 'left center' : 'right center';
                    },
                    focusNote() {
                        this.$nextTick(() => {
                            this.$refs.note?.focus?.();
                            const el = this.$refs.note?.querySelector?.('textarea') || this.$refs.note;
                            el?.focus?.();
                        });
                    }
                }"
                x-init="$nextTick(() => { place(); requestAnimationFrame(() => place()); focusNote(); })"
                x-on:resize.window.debounce.50ms="place()"
                x-on:scroll.window.capture="place()"
            >
                <button
                    type="button"
                    class="fixed inset-0 z-40 bg-zinc-950/25 md:bg-transparent"
                    wire:click="cancelPin"
                    aria-label="Dismiss note"
                ></button>

                <div
                    x-ref="panel"
                    class="rm-note-composer fixed inset-x-0 bottom-0 z-50 max-h-[min(78svh,34rem)] overflow-y-auto rounded-t-2xl border border-zinc-200 bg-white p-4 shadow-[0_-12px_40px_-18px_rgba(24,24,27,0.45)] md:inset-x-auto md:bottom-auto md:w-[min(20rem,calc(100vw-1rem))] md:rounded-2xl md:p-3.5 md:shadow-[0_18px_50px_-24px_rgba(24,24,27,0.45)]"
                    role="dialog"
                    aria-label="{{ $mode === 'guest' ? 'Suggest a change' : 'Leave a note' }}"
                    x-on:keydown.escape.window="$wire.cancelPin()"
                >
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-zinc-200 md:hidden" aria-hidden="true"></div>
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <flux:heading size="sm">
                            @if ($pendingW !== null && $pendingH !== null && $pendingW >= 0.01 && $pendingH >= 0.01)
                                {{ $mode === 'guest' ? 'Suggest a change here' : 'Leave a note here' }}
                            @else
                                {{ $mode === 'guest' ? 'Suggest a change on this spot' : 'Leave a note on this spot' }}
                            @endif
                        </flux:heading>
                        <button
                            type="button"
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700"
                            wire:click="cancelPin"
                            aria-label="Cancel"
                        >
                            <flux:icon.x-mark class="size-4" />
                        </button>
                    </div>
                    <div class="space-y-3">
                        @if ($mode === 'guest')
                            <flux:input
                                wire:model="guestName"
                                placeholder="Your name"
                                maxlength="40"
                                x-data
                                x-init="if (! $wire.guestName) { $wire.guestName = localStorage.getItem('revisemy_guest_name') || '' }"
                                x-on:change="if ($event.target.value) { localStorage.setItem('revisemy_guest_name', $event.target.value) }"
                            />
                            <flux:error name="guestName" />
                        @endif
                        <div x-ref="note">
                            <flux:textarea wire:model="draftBody" rows="3" placeholder="Be specific — what feels off, and what would be better?" />
                            <flux:error name="draftBody" />
                        </div>
                        @if ($mode === 'owner')
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Models\Annotation::severityLabels() as $value => $label)
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-sm has-[:checked]:border-zinc-400 has-[:checked]:bg-white has-[:checked]:shadow-sm">
                                    <input type="radio" wire:model="draftSeverity" value="{{ $value }}" class="{{ \App\Models\Annotation::accentClass($value) }}">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @endif
                        <div class="flex flex-col gap-2 sm:flex-row">
                            @if ($mode === 'guest')
                                <flux:button variant="primary" icon="chat-bubble-left-ellipsis" wire:click="savePin" class="w-full !bg-yellow-400 !text-zinc-900 hover:!bg-yellow-300 sm:w-auto">Suggest</flux:button>
                            @else
                                <flux:button variant="primary" icon="check" wire:click="savePin" class="w-full !bg-rose-600 sm:w-auto">Save mark</flux:button>
                            @endif
                            <flux:button variant="ghost" icon="x-mark" wire:click="cancelPin" class="w-full sm:w-auto">Cancel</flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <aside class="flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto border-t border-zinc-200 px-0.5 pt-4 pb-28 [scroll-padding-top:1rem] sm:pt-5 xl:min-h-0 xl:border-t-0 xl:overflow-y-auto xl:pt-6 xl:pb-6 xl:[scroll-padding-top:1.5rem] lg:pb-6">
            <div class="space-y-4 pb-4 xl:pb-6">
            <div class="rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4">
                <flux:heading size="sm" class="mb-3">{{ $mode === 'owner' ? 'My marks' : 'Owner marks' }}</flux:heading>

                @php($pins = $shot?->annotations ?? collect())

                @if ($pins->isEmpty())
                    <p class="text-sm text-zinc-500">
                        {{ $mode === 'owner' ? 'No marks yet. Drag to outline a region, or click for a point.' : 'The owner has not marked anything yet.' }}
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($pins as $pin)
                            <li
                                id="fb-mark-{{ $pin->id }}"
                                class="rounded-xl border border-zinc-100 p-3 transition"
                                x-bind:class="$store.rmFocus?.mark === {{ $pin->id }} ? 'border-rose-300 ring-2 ring-rose-200/70' : ''"
                            >
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
                                        <span class="text-xs text-zinc-500">{{ $pin->label() }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $pin->statusBadgeClass() }}">{{ $pin->statusLabel() }}</span>
                                    </div>
                                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                                        <button type="button" class="text-xs text-zinc-400 hover:text-rose-600" wire:click="deletePin({{ $pin->id }})" wire:confirm="Remove this mark?">Remove</button>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700">{{ $pin->body }}</p>
                                @if ($pin->resolution_note)
                                    <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900">
                                        <span class="font-medium">Agent:</span> {{ $pin->resolution_note }}
                                    </div>
                                @endif
                                <x-mark-before-after :mark="$pin" />
                                @if ($pin->comments->isNotEmpty())
                                    <div class="mt-2 space-y-1.5 border-t border-zinc-100 pt-2">
                                        @foreach ($pin->comments as $comment)
                                            <div wire:key="pin-comment-{{ $comment->id }}" class="rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                <div class="mb-0.5 flex flex-wrap items-baseline justify-between gap-x-2">
                                                    <span class="text-[11px] font-medium text-zinc-700">{{ $comment->author }}</span>
                                                    <time
                                                        class="text-[10px] text-zinc-400"
                                                        datetime="{{ $comment->created_at->toIso8601String() }}"
                                                        title="{{ $comment->created_at->timezone(config('app.timezone'))->toDayDateTimeString() }}"
                                                    >{{ $comment->created_at->diffForHumans() }}</time>
                                                </div>
                                                <p class="text-xs leading-relaxed text-zinc-600">{{ $comment->body }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="mt-2">
                                    @if ($review->allowsComments())
                                        @if ($activeCommentMarkId === $pin->id)
                                            <div class="space-y-2 rounded-lg border border-zinc-200 bg-zinc-50/80 p-2">
                                                @if ($mode === 'guest')
                                                    <flux:input
                                                        wire:model="guestName"
                                                        placeholder="Your name"
                                                        maxlength="40"
                                                        size="sm"
                                                        x-data
                                                        x-init="if (! $wire.guestName) { $wire.guestName = localStorage.getItem('revisemy_guest_name') || '' }"
                                                        x-on:change="if ($event.target.value) { localStorage.setItem('revisemy_guest_name', $event.target.value) }"
                                                    />
                                                    <flux:error name="guestName" />
                                                @endif
                                                <flux:textarea wire:model="markCommentBody" rows="2" placeholder="Add a comment…" />
                                                <flux:error name="markCommentBody" />
                                                <div class="flex gap-2">
                                                    <flux:button size="sm" variant="primary" wire:click="addMarkComment({{ $pin->id }})" class="!bg-zinc-900 hover:!bg-zinc-800">Post</flux:button>
                                                    <flux:button size="sm" variant="ghost" wire:click="cancelMarkComment">Cancel</flux:button>
                                                </div>
                                            </div>
                                        @else
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-500 transition hover:text-zinc-800"
                                                wire:click="startMarkComment({{ $pin->id }})"
                                            >
                                                Comment
                                                @if ($pin->comments->isNotEmpty())
                                                    <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-zinc-100 px-1.5 text-[10px] font-medium tabular-nums text-zinc-600">{{ $pin->comments->count() }}</span>
                                                @endif
                                            </button>
                                        @endif
                                    @elseif ($pin->comments->isNotEmpty())
                                        <p class="inline-flex items-center gap-1.5 text-[11px] text-zinc-400">
                                            <span>Comments</span>
                                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-zinc-100 px-1.5 text-[10px] font-medium tabular-nums text-zinc-600">{{ $pin->comments->count() }}</span>
                                            <span>· commenting off</span>
                                        </p>
                                    @endif
                                </div>
                                @if ($mode === 'owner' && $pin->severity !== \App\Models\Annotation::SEVERITY_KEEP && $this->canManageMarks())
                                    <div class="mt-2 flex items-center gap-2">
                                        @if ($pin->awaitsVerification())
                                            <button type="button" class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white transition hover:bg-emerald-500" wire:click="verifyMark({{ $pin->id }})">Verify</button>
                                        @endif
                                        @if ($pin->status !== \App\Models\Annotation::STATUS_OPEN)
                                            <button type="button" class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-200" wire:click="reopenMark({{ $pin->id }})">Reopen</button>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @php($parent = $review->parent)
            @if ($mode === 'owner' && $parent)
                @php($previousMarks = $parent->screenshots->flatMap->annotations->sortBy('number')->values())
                @if ($previousMarks->isNotEmpty())
                    <div class="rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4">
                        <flux:heading size="sm" class="mb-1">Previous pass marks</flux:heading>
                        <p class="mb-3 text-xs leading-snug text-zinc-500">From pass {{ $parent->pass }}. Verify what the agent fixed, or reopen anything still off.</p>
                        <ul class="space-y-3">
                            @foreach ($previousMarks as $pin)
                                <li class="rounded-xl border border-zinc-100 p-3">
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white {{ $pin->markerClass() }}">M{{ $pin->number }}</span>
                                        <span class="text-xs text-zinc-500">{{ $pin->label() }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium {{ $pin->statusBadgeClass() }}">{{ $pin->statusLabel() }}</span>
                                    </div>
                                    <p class="text-sm leading-relaxed text-zinc-700">{{ $pin->body }}</p>
                                    @if ($pin->resolution_note)
                                        <div class="mt-2 rounded-lg bg-emerald-50/70 px-2.5 py-1.5 text-xs leading-relaxed text-emerald-900">
                                            <span class="font-medium">Agent:</span> {{ $pin->resolution_note }}
                                        </div>
                                    @endif
                                    <x-mark-before-after :mark="$pin" />
                                    @if ($pin->comments->isNotEmpty())
                                        <div class="mt-2 space-y-1.5 border-t border-zinc-100 pt-2">
                                            @foreach ($pin->comments as $comment)
                                                <div wire:key="prev-pin-comment-{{ $comment->id }}" class="rounded-lg bg-zinc-50 px-2.5 py-1.5">
                                                    <div class="mb-0.5 flex flex-wrap items-baseline justify-between gap-x-2">
                                                        <span class="text-[11px] font-medium text-zinc-700">{{ $comment->author }}</span>
                                                        <time
                                                            class="text-[10px] text-zinc-400"
                                                            datetime="{{ $comment->created_at->toIso8601String() }}"
                                                            title="{{ $comment->created_at->timezone(config('app.timezone'))->toDayDateTimeString() }}"
                                                        >{{ $comment->created_at->diffForHumans() }}</time>
                                                    </div>
                                                    <p class="text-xs leading-relaxed text-zinc-600">{{ $comment->body }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($pin->severity !== \App\Models\Annotation::SEVERITY_KEEP && $this->canManageMarks())
                                        <div class="mt-2 flex items-center gap-2">
                                            @if ($pin->awaitsVerification())
                                                <button type="button" class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white transition hover:bg-emerald-500" wire:click="verifyMark({{ $pin->id }})">Verify</button>
                                            @endif
                                            @if ($pin->status !== \App\Models\Annotation::STATUS_OPEN)
                                                <button type="button" class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-200" wire:click="reopenMark({{ $pin->id }})">Reopen</button>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif

            @if ($mode === 'owner')
            <div class="rounded-2xl border border-sky-200/80 bg-sky-50/50 p-3 shadow-sm sm:p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <flux:heading size="sm">Second opinion</flux:heading>
                        <p class="mt-1 text-xs leading-snug text-zinc-500">Hints until you accept — then they become your marks</p>
                    </div>
                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-path"
                            wire:click="refreshSecondOpinion"
                            wire:loading.attr="disabled"
                            class="shrink-0 [&_[data-flux-loading-indicator]]:rounded-md [&_[data-flux-loading-indicator]]:bg-sky-100/95"
                        >
                            Refresh
                        </flux:button>
                    @endif
                </div>

                @php($findings = ($shot?->findings ?? collect())->filter(fn ($f) => $f->isOpen() && ! $f->isGuest())->values())
                @php($status = $shot?->second_opinion_status ?? 'idle')
                @php($findingNumbers = $suggestionNumbers['s'])
                @php($sourceTabLabels = [
                    'all' => 'All',
                    'checklist' => 'Checklist',
                    'vision' => 'Vision',
                ])
                @php($sourceTabCounts = [
                    'all' => $findings->count(),
                    'checklist' => $findings->filter(fn ($f) => $f->isChecklistSource())->count(),
                    'vision' => $findings->filter(fn ($f) => $f->isVisionSource())->count(),
                ])
                @php($sourceFilteredFindings = match ($secondOpinionSourceTab) {
                    'checklist' => $findings->filter(fn ($f) => $f->isChecklistSource())->values(),
                    'vision' => $findings->filter(fn ($f) => $f->isVisionSource())->values(),
                    default => $findings,
                })
                @php($tabCounts = [
                    'all' => $sourceFilteredFindings->count(),
                    Finding::SEVERITY_SUGGESTION => $sourceFilteredFindings->where('severity', Finding::SEVERITY_SUGGESTION)->count(),
                    Finding::SEVERITY_A11Y => $sourceFilteredFindings->where('severity', Finding::SEVERITY_A11Y)->count(),
                    Finding::SEVERITY_POLISH => $sourceFilteredFindings->where('severity', Finding::SEVERITY_POLISH)->count(),
                ])
                @php($tabLabels = [
                    'all' => 'All',
                    Finding::SEVERITY_SUGGESTION => 'Suggestion',
                    Finding::SEVERITY_A11Y => 'A11y',
                    Finding::SEVERITY_POLISH => 'Polish',
                ])
                @php($visibleFindings = $secondOpinionTab === 'all'
                    ? $sourceFilteredFindings
                    : $sourceFilteredFindings->where('severity', $secondOpinionTab)->values())

                @if ($status === 'queued')
                    <p class="mb-2 text-sm text-sky-700">{{ $findings->isEmpty() ? 'Generating hints…' : 'Adding vision hints…' }}</p>
                @elseif ($status === 'failed')
                    <p class="mb-2 text-sm text-rose-600">Second opinion failed{{ $shot?->second_opinion_error ? ': '.$shot->second_opinion_error : '.' }}</p>
                @endif

                @if ($mode === 'owner' && ! app(SecondOpinionService::class)->visionEnabled())
                    <p class="mb-3 rounded-lg border border-sky-100 bg-white/80 px-2.5 py-2 text-xs leading-relaxed text-sky-900">
                        Checklist hints stay in this sidebar. Add <span class="font-mono text-[10px]">ANTHROPIC_API_KEY</span> or <span class="font-mono text-[10px]">OPENAI_API_KEY</span> on the server (and a queue worker) for vision hints that mark regions on the capture.
                    </p>
                @endif

                @if ($findings->isEmpty() && $status !== 'queued')
                    <p class="text-sm text-zinc-500">No open suggestions. Accept ones you want as marks, dismiss the rest, or refresh for a new pass.</p>
                @elseif ($findings->isNotEmpty())
                    <div class="mb-2 overflow-x-auto overscroll-x-contain rounded-full border border-sky-300/80 bg-sky-50/60 p-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <div class="flex w-max gap-1">
                            @foreach ($sourceTabLabels as $sourceTabId => $sourceTabLabel)
                                @if ($sourceTabId === 'all' || $sourceTabCounts[$sourceTabId] > 0 || ($sourceTabId === 'vision' && app(SecondOpinionService::class)->visionEnabled()))
                                    <button
                                        type="button"
                                        wire:click="setSecondOpinionSourceTab('{{ $sourceTabId }}')"
                                        @class([
                                            'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition',
                                            'border border-sky-300/80 bg-white text-sky-900 shadow-sm' => $secondOpinionSourceTab === $sourceTabId,
                                            'border border-transparent text-sky-800/80 hover:bg-white/70 hover:text-sky-900' => $secondOpinionSourceTab !== $sourceTabId,
                                        ])
                                    >
                                        <span>{{ $sourceTabLabel }}</span>
                                        <span @class([
                                            'rounded-full px-1.5 py-px text-[10px] tabular-nums',
                                            'font-semibold text-sky-800' => $secondOpinionSourceTab === $sourceTabId,
                                            'bg-sky-100/80 text-sky-700' => $secondOpinionSourceTab !== $sourceTabId,
                                        ])>{{ $sourceTabCounts[$sourceTabId] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3 overflow-x-auto overscroll-x-contain rounded-full border border-sky-200/80 bg-white/80 p-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <div class="flex w-max gap-1">
                            @foreach ($tabLabels as $tabId => $tabLabel)
                                @if ($tabId === 'all' || $tabCounts[$tabId] > 0)
                                    <button
                                        type="button"
                                        wire:click="setSecondOpinionTab('{{ $tabId }}')"
                                        @class([
                                            'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-medium whitespace-nowrap transition',
                                            'border border-sky-200/80 bg-sky-100 text-sky-800' => $secondOpinionTab === $tabId,
                                            'border border-transparent text-zinc-500 hover:bg-sky-50/80 hover:text-sky-800' => $secondOpinionTab !== $tabId,
                                        ])
                                    >
                                        <span>{{ $tabLabel }}</span>
                                        <span @class([
                                            'rounded-full px-1.5 py-px text-[10px] tabular-nums',
                                            'font-semibold text-sky-700' => $secondOpinionTab === $tabId,
                                            'bg-zinc-100 text-zinc-500' => $secondOpinionTab !== $tabId,
                                        ])>{{ $tabCounts[$tabId] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-2 flex items-center justify-between gap-2" x-show="$store.rmFocus?.finding" x-cloak>
                        <p class="text-xs text-sky-700">Focused on one hint</p>
                        <button
                            type="button"
                            class="text-xs font-medium text-sky-700 hover:text-sky-900"
                            x-on:click="$store.rmFocus.finding = null"
                        >Show all</button>
                    </div>

                    @if ($visibleFindings->isEmpty())
                        <p class="text-sm text-zinc-500">
                            @if ($secondOpinionSourceTab === 'vision')
                                No open vision hints{{ $secondOpinionTab !== 'all' ? ' in this category' : '' }}.
                            @elseif ($secondOpinionSourceTab === 'checklist')
                                No open checklist hints{{ $secondOpinionTab !== 'all' ? ' in this category' : '' }}.
                            @else
                                No open {{ strtolower($tabLabels[$secondOpinionTab] ?? $secondOpinionTab) }} hints.
                            @endif
                        </p>
                    @else
                        <ul class="space-y-3">
                            @foreach ($visibleFindings as $finding)
                                @php($findingArea = is_array($finding->area) ? $finding->area : null)
                                @php($findingHasRegion = $findingArea && (float) ($findingArea['w'] ?? 0) >= 0.01 && (float) ($findingArea['h'] ?? 0) >= 0.01)
                                <li
                                    id="fb-finding-{{ $finding->id }}"
                                    class="cursor-pointer rounded-xl border bg-white/80 p-3 transition"
                                    x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                    x-on:click="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                    x-bind:class="$store.rmFocus?.finding === {{ $finding->id }}
                                        ? 'border-sky-400 ring-2 ring-sky-300/60'
                                        : 'border-sky-100'"
                                >
                                    <div class="mb-1 flex items-start gap-2">
                                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-sky-500 text-[10px] font-semibold text-sky-700">S{{ $findingNumbers[$finding->id] ?? '' }}</span>
                                            <span class="text-xs text-zinc-500">{{ \App\Models\Annotation::allSeverityLabels()[$finding->severity] ?? $finding->severity }}</span>
                                            @if (! $findingHasRegion)
                                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-500">Text hint</span>
                                            @endif
                                            <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-medium text-sky-800">{{ $finding->sourceLabel() }}</span>
                                        </div>
                                        @if ($review->isOpenForFeedback() && $mode === 'owner')
                                            <div class="flex shrink-0 items-center gap-1" x-on:click.stop>
                                                <button
                                                    type="button"
                                                    wire:click="acceptFinding({{ $finding->id }})"
                                                    title="Accept as mark"
                                                    aria-label="Accept as mark"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-sky-600 text-white transition hover:bg-sky-500"
                                                >
                                                    <flux:icon.check variant="micro" class="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="dismissFinding({{ $finding->id }})"
                                                    title="Dismiss"
                                                    aria-label="Dismiss"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700"
                                                >
                                                    <flux:icon.x-mark variant="micro" class="size-3.5" />
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-sm leading-relaxed text-zinc-700">{{ $finding->body }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
            @endif

            <div class="rounded-2xl border border-amber-200/80 bg-amber-50/50 p-3 shadow-sm sm:p-4">
                <div class="mb-3">
                    <flux:heading size="sm">Guest feedback</flux:heading>
                    <p class="mt-1 text-xs leading-snug text-zinc-500">
                        {{ $mode === 'owner' ? 'Teammate suggestions — accept to make them your marks' : 'Suggestions from you and other guests' }}
                    </p>
                </div>

                @php($guestSuggestions = ($shot?->findings ?? collect())->filter(fn ($f) => $f->isOpen() && $f->isGuest())->values())

                @if ($guestSuggestions->isEmpty())
                    <p class="text-sm text-zinc-500">
                        {{ $mode === 'owner' ? 'No guest suggestions yet. Use Share above to copy or regenerate the guest link.' : 'No suggestions yet. Drag or click on the screenshot to add one.' }}
                    </p>
                @else
                    <ul class="space-y-3">
                        @foreach ($guestSuggestions as $guestIndex => $finding)
                            <li
                                id="fb-finding-{{ $finding->id }}"
                                class="cursor-pointer rounded-xl border bg-white/80 p-3 transition"
                                x-show="! $store.rmFocus?.finding || $store.rmFocus.finding === {{ $finding->id }}"
                                x-on:click="$store.rmFocus.finding = $store.rmFocus.finding === {{ $finding->id }} ? null : {{ $finding->id }}"
                                x-bind:class="$store.rmFocus?.finding === {{ $finding->id }}
                                    ? 'border-amber-400 ring-2 ring-amber-300/60'
                                    : 'border-amber-100'"
                            >
                                <div class="mb-1 flex items-start gap-2">
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-amber-500 text-[10px] font-semibold text-amber-700">G{{ $suggestionNumbers['g'][$finding->id] ?? ($guestIndex + 1) }}</span>
                                        <span class="text-xs text-zinc-500">{{ \App\Models\Annotation::allSeverityLabels()[$finding->severity] ?? $finding->severity }}</span>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">{{ $finding->sourceLabel() }}</span>
                                    </div>
                                    @if ($review->isOpenForFeedback() && $mode === 'owner')
                                        <div class="flex shrink-0 items-center gap-1" x-on:click.stop>
                                            <button
                                                type="button"
                                                wire:click="acceptFinding({{ $finding->id }})"
                                                title="Accept as mark"
                                                aria-label="Accept as mark"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-amber-600 text-white transition hover:bg-amber-500"
                                            >
                                                <flux:icon.check variant="micro" class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="dismissFinding({{ $finding->id }})"
                                                title="Dismiss"
                                                aria-label="Dismiss"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700"
                                            >
                                                <flux:icon.x-mark variant="micro" class="size-3.5" />
                                            </button>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700">{{ $finding->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            </div>

            @if ($this->showDecisionNote() || $this->showDecisionCallout() || $this->showStatusCallout())
                <div class="mt-auto space-y-3 border-t border-zinc-200 pt-4 pb-4 xl:pb-6">
                    @if ($this->showDecisionNote())
                        <div class="hidden rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4 lg:block">
                            <flux:heading size="sm" class="mb-3">Overall note (optional)</flux:heading>
                            <flux:textarea wire:model="decisionNote" rows="2" placeholder="Anything else before you approve or request changes?" />
                        </div>
                    @elseif ($this->showDecisionCallout())
                        <flux:callout>
                            <strong class="font-medium">Note to the agent:</strong> {{ $review->decision_note }}
                        </flux:callout>
                    @endif

                    @if ($review->effectiveStatus() === 'changes_requested')
                        <flux:callout>
                            <strong class="font-medium">What’s next:</strong>
                            The agent should apply your marks, then open a new checkup pass with fresh screenshots (linked to this review). You’ll get another link to approve.
                        </flux:callout>
                    @elseif ($review->effectiveStatus() === 'approved')
                        <flux:callout>
                            <strong class="font-medium">Loop complete for this pass.</strong>
                            Ask the agent for another checkup anytime if the UI changes again.
                        </flux:callout>
                    @endif
                </div>
            @endif
        </aside>
    </div>

    @if ($this->isOwner() && $review->isOpenForFeedback())
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 p-3 shadow-[0_-8px_30px_-12px_rgba(24,24,27,0.25)] backdrop-blur lg:hidden">
            <flux:textarea wire:model="decisionNote" rows="2" placeholder="Overall note (optional)…" class="mb-2" />
            <div class="flex gap-2">
                <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="requestChanges" wire:confirm="Request changes and send marks back to the agent?" class="min-w-0 flex-1 !bg-zinc-100 hover:!bg-zinc-200/80">Changes</flux:button>
                <flux:button variant="primary" icon="check" wire:click="approve" wire:confirm="Approve this pass? Resolved marks will be verified and the loop closes." class="min-w-0 flex-1 !bg-rose-600 hover:!bg-rose-700">Approve</flux:button>
            </div>
        </div>
    @endif
    @endif
</div>
