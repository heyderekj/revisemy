<?php

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Review;
use App\Models\Screenshot;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\SecondOpinionService;
use App\Support\FeedbackText;
use Livewire\Attributes\Computed;
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

    public string $draftSuggestedCopy = '';

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

    public string $questionAnswerDraft = '';

    public ?int $answeringMarkId = null;

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
            ->with(['screenshots.annotations.comments', 'screenshots.annotations.afterScreenshot', 'screenshots.findings'])
            ->firstOrFail();

        $this->mode = hash_equals((string) $review->token, $this->token) ? 'owner' : 'guest';

        // The previous-pass strip and the pass ledger are owner-only, and this
        // runs on a 3s poll — so guests and first passes never pay for the
        // parent's screenshots, annotations, and comment threads.
        if ($this->mode === 'owner' && $review->parent_id !== null) {
            $review->load([
                'parent.screenshots.annotations.comments',
                'parent.screenshots.annotations.afterScreenshot',
            ]);
        }

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
     * Fall back to All severity when that category has no open findings left.
     * Source tabs (Checklist / Vision) stay selected even when empty so Vision
     * can show its setup empty state.
     */
    protected function syncSecondOpinionTab(): void
    {
        if ($this->secondOpinionTab === 'all') {
            return;
        }

        $shot = $this->review->screenshots->values()->get($this->activeScreenshotIndex);
        $findings = ($shot?->findings ?? collect())
            ->filter(fn (Finding $f) => $f->isOpen() && ! $f->isGuest());

        $sourceFiltered = $this->filterFindingsBySource($findings, $this->secondOpinionSourceTab);
        $hasTab = $sourceFiltered->contains(fn (Finding $f) => $f->severity === $this->secondOpinionTab);

        if (! $hasTab) {
            $this->secondOpinionTab = 'all';
        }
    }

    public function visionEnabled(): bool
    {
        return app(SecondOpinionService::class)->visionEnabled();
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
        $this->draftSuggestedCopy = '';
        $this->draftSeverity = Annotation::SEVERITY_MUST_FIX;
    }

    public function cancelPin(): void
    {
        $this->pendingX = null;
        $this->pendingY = null;
        $this->pendingW = null;
        $this->pendingH = null;
        $this->draftBody = '';
        $this->draftSuggestedCopy = '';
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
        $this->draftSuggestedCopy = FeedbackText::sanitizeBody($this->draftSuggestedCopy);
        $this->guestName = FeedbackText::sanitizeName($this->guestName);

        $rules = [
            'draftBody' => FeedbackText::bodyRules(),
            'draftSeverity' => ['required', 'in:'.implode(',', Annotation::severities())],
            'draftSuggestedCopy' => ['nullable', 'string', 'max:2000'],
        ];

        $messages = [
            'draftBody.required' => 'Leave a note on this spot.',
        ];

        if (! $this->isOwner()) {
            $this->draftSeverity = Annotation::SEVERITY_MUST_FIX;
            FeedbackText::throttleGuest($this->review->id);
            $rules['guestName'] = FeedbackText::nameRules();
            unset($rules['draftSeverity'], $rules['draftSuggestedCopy']);
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
                [
                    'suggested_copy' => $this->draftSuggestedCopy !== '' ? $this->draftSuggestedCopy : null,
                    'source' => Annotation::SOURCE_HUMAN,
                ],
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

    public function verifyAllResolved(MarkLifecycleService $lifecycle): void
    {
        if (! $this->canManageMarks()) {
            return;
        }

        $lifecycle->verifyAllResolved($this->review);
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

    public function startAnswerQuestion(int $annotationId): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $annotation = $this->ownedAnnotation($annotationId);

        if (! $annotation || $annotation->severity !== Annotation::SEVERITY_QUESTION) {
            return;
        }

        $this->answeringMarkId = $annotationId;
        $this->questionAnswerDraft = (string) ($annotation->question_answer ?? '');
        $this->resetValidation(['questionAnswerDraft']);
    }

    public function cancelAnswerQuestion(): void
    {
        $this->answeringMarkId = null;
        $this->questionAnswerDraft = '';
        $this->resetValidation(['questionAnswerDraft']);
    }

    public function answerQuestion(int $annotationId, MarkLifecycleService $lifecycle): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $annotation = $this->ownedAnnotation($annotationId);

        if (! $annotation || $annotation->severity !== Annotation::SEVERITY_QUESTION) {
            return;
        }

        $this->questionAnswerDraft = FeedbackText::sanitizeBody($this->questionAnswerDraft);

        $this->validate([
            'questionAnswerDraft' => FeedbackText::bodyRules(1000),
        ], [
            'questionAnswerDraft.required' => 'Write the answer the agent should follow.',
        ]);

        $lifecycle->answerQuestion($annotation, $this->questionAnswerDraft);

        $this->cancelAnswerQuestion();
        $this->loadReview();
    }

    /**
     * Marks on this pass waiting for the human to verify agent fixes.
     */
    public function awaitingVerificationMarks()
    {
        return $this->review->screenshots
            ->flatMap->annotations
            ->filter(fn (Annotation $mark) => $mark->awaitsVerification())
            ->sortBy('number')
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function passLedgerEntries(): array
    {
        return $this->review->passLedger();
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

    public function acceptFinding(int $findingId, ?string $asSeverity = null): void
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

        $this->promoteFinding($finding, $asSeverity);
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

    /**
     * Batch-accept open findings on the active screenshot.
     * $panel: "second" (non-guest) or "guest".
     */
    public function acceptOpenFindings(string $panel = 'second'): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $shot = $this->activeScreenshot;

        if (! $shot) {
            return;
        }

        $findings = $shot->findings
            ->filter(fn (Finding $finding) => $finding->isOpen())
            ->filter(fn (Finding $finding) => $panel === 'guest' ? $finding->isGuest() : ! $finding->isGuest())
            ->values();

        foreach ($findings as $finding) {
            $this->promoteFinding($finding);
        }

        $this->loadReview();
    }

    /**
     * Batch-dismiss open findings on the active screenshot.
     * $panel: "second" (non-guest) or "guest".
     */
    public function dismissOpenFindings(string $panel = 'second'): void
    {
        if (! $this->isOwner() || ! $this->review->isOpenForFeedback()) {
            return;
        }

        $shot = $this->activeScreenshot;

        if (! $shot) {
            return;
        }

        $findings = $shot->findings
            ->filter(fn (Finding $finding) => $finding->isOpen())
            ->filter(fn (Finding $finding) => $panel === 'guest' ? $finding->isGuest() : ! $finding->isGuest())
            ->values();

        foreach ($findings as $finding) {
            $finding->update(['status' => Finding::STATUS_DISMISSED]);
        }

        $this->loadReview();
    }

    protected function promoteFinding(Finding $finding, ?string $asSeverity = null): void
    {
        if (! $finding->isOpen()) {
            return;
        }

        $severity = $asSeverity && in_array($asSeverity, Annotation::severities(), true)
            ? $asSeverity
            : $finding->pinSeverity();

        $screenshot = $finding->screenshot;
        $area = $finding->region();

        $x = $finding->x !== null
            ? (float) $finding->x
            : ($area ? (float) $area['x'] + ((float) $area['w'] / 2) : 0.5);
        $y = $finding->y !== null
            ? (float) $finding->y
            : ($area ? (float) $area['y'] + ((float) $area['h'] / 2) : 0.5);

        $pin = app(MarkLifecycleService::class)->createMark(
            $screenshot,
            (float) $x,
            (float) $y,
            $area,
            $severity,
            $finding->body,
            [
                'source' => Annotation::sourceFromFinding($finding),
                'promoted_from_finding_id' => $finding->id,
            ],
        );

        $finding->update([
            'status' => Finding::STATUS_ACCEPTED,
            'related_pin' => $pin->number,
        ]);
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

    /**
     * Marks on the shot being viewed. Cached because the canvas overlay, the
     * mark strip, and the sidebar each ask for the same collection on every
     * render — and this component re-renders on a poll.
     *
     * @return \Illuminate\Support\Collection<int, Annotation>
     */
    #[Computed]
    public function activeMarks()
    {
        return $this->activeScreenshot?->annotations ?? collect();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Finding>
     */
    #[Computed]
    public function openSecondOpinion()
    {
        return ($this->activeScreenshot?->findings ?? collect())
            ->filter(fn (Finding $f) => $f->isOpen() && ! $f->isGuest())
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Finding>
     */
    #[Computed]
    public function openGuestSuggestions()
    {
        return ($this->activeScreenshot?->findings ?? collect())
            ->filter(fn (Finding $f) => $f->isOpen() && $f->isGuest())
            ->values();
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
    @include('review.partials.header')

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
        class="mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden px-4 sm:px-6 md:grid md:grid-cols-[minmax(0,1fr)_18rem] md:gap-x-5 md:overflow-hidden lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-x-6"
        x-data
        x-init="
            if (! Alpine.store('rmFocus')) {
                Alpine.store('rmFocus', { finding: null, mark: null });
            }
        "
    >
        {{-- Shared by both panes: @include passes locals down, never back up. --}}
        @php($shot = $this->activeScreenshot)
        @php($suggestionNumbers = $review->suggestionDisplayNumbers())

        @include('review.partials.canvas')

        @include('review.partials.sidebar')
    </div>

    @if ($this->isOwner() && $review->isOpenForFeedback())
        @include('review.partials.mobile-decision-bar')
    @endif
    @endif
</div>
