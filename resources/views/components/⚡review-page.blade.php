<?php

use App\Livewire\Concerns\FindsReviewMarks;
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
    use FindsReviewMarks;

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

    /** Which hints show: all, one second-opinion category, or guest suggestions. */
    public string $hintFilter = 'all';


    public string $markCommentBody = '';

    public ?int $activeCommentMarkId = null;

    /**
     * The last thing that can be taken back, for the toast's Undo: Koati's
     * way of deleting without asking first. Locked, so the browser can't
     * write its own.
     *
     * @var array{kind: string, at: int, mark?: array<string, mixed>, comments?: list<array<string, mixed>>, findings?: list<int>, pins?: list<int>}|null
     */
    #[Locked]
    public ?array $undoable = null;

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

        $this->syncHintFilter();
    }

    public function isOwner(): bool
    {
        return $this->mode === 'owner';
    }

    /** This browser connected the workspace behind the review, so it can jump to the rest. */
    public function canListReviews(): bool
    {
        return $this->isOwner() && auth('web')->user()?->workspace_id === $this->review->workspace_id;
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
        return $this->isOwner() && in_array($this->review->effectiveStatus(), ['changes_requested', 'approved'], true);
    }

    public function selectScreenshot(int $index): void
    {
        $this->activeScreenshotIndex = $index;
        $this->hintFilter = 'all';
        $this->cancelPin();
    }

    public function setHintFilter(string $filter): void
    {
        $allowed = ['all', 'guest', Finding::SEVERITY_SUGGESTION, Finding::SEVERITY_A11Y, Finding::SEVERITY_POLISH];

        if (in_array($filter, $allowed, true)) {
            $this->hintFilter = $filter;
        }
    }

    /** Back to All when the chosen kind has no open hints left. */
    protected function syncHintFilter(): void
    {
        if ($this->hintFilter !== 'all' && $this->visibleHints()->isEmpty()) {
            $this->hintFilter = 'all';
        }
    }

    /**
     * Open hints on this shot, second opinion first then guests, narrowed by
     * the filter. Second opinion is the owner's alone.
     *
     * @return \Illuminate\Support\Collection<int, Finding>
     */
    public function visibleHints()
    {
        $hints = $this->isOwner()
            ? $this->openSecondOpinion->concat($this->openGuestSuggestions)
            : $this->openGuestSuggestions;

        return match ($this->hintFilter) {
            'all' => $hints->values(),
            'guest' => $hints->filter(fn (Finding $f) => $f->isGuest())->values(),
            default => $hints->filter(fn (Finding $f) => ! $f->isGuest() && $f->severity === $this->hintFilter)->values(),
        };
    }

    public function visionEnabled(): bool
    {
        return app(SecondOpinionService::class)->visionEnabled();
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
            ->with('comments')
            ->first();

        if (! $annotation) {
            return;
        }

        // Kept whole, comments too, so Undo puts back exactly what was there.
        $this->offerUndo('Removed M'.$annotation->number, [
            'kind' => 'mark',
            'mark' => $annotation->getAttributes(),
            'comments' => $annotation->comments->map->getAttributes()->all(),
        ]);

        $annotation->delete();
        $this->loadReview();
    }

    /**
     * Remember what just happened and show the toast. Only the latest action
     * can be undone, and only for a short while (UNDO_SECONDS).
     *
     * @param  array<string, mixed>  $state
     */
    protected function offerUndo(string $message, array $state): void
    {
        $this->undoable = $state + ['at' => now()->timestamp];
        $this->dispatch('undoable', message: $message);
    }

    public const UNDO_SECONDS = 15;

    public function undo(): void
    {
        $state = $this->undoable;
        $this->undoable = null;

        if (! $state || ! $this->isOwner() || ! $this->review->isOpenForFeedback()
            || now()->timestamp - (int) $state['at'] > self::UNDO_SECONDS) {
            return;
        }

        $shotIds = $this->review->screenshots()->pluck('id');

        if ($state['kind'] === 'mark' && $shotIds->contains($state['mark']['screenshot_id'] ?? null)) {
            Annotation::query()->insert($state['mark']);

            foreach ($state['comments'] ?? [] as $comment) {
                \App\Models\AnnotationComment::query()->insert($comment);
            }
        }

        if ($state['kind'] === 'findings') {
            // Marks made from the hints go; the hints come back open.
            Annotation::query()
                ->whereKey($state['pins'] ?? [])
                ->whereIn('screenshot_id', $shotIds)
                ->delete();

            Finding::query()
                ->whereKey($state['findings'] ?? [])
                ->whereIn('screenshot_id', $shotIds)
                ->update(['status' => Finding::STATUS_OPEN, 'related_pin' => null]);
        }

        $this->loadReview();
    }

    /**
     * Owner can verify/reopen marks while waiting on the first look and after
     * requesting changes (so the agent's resolutions can be checked next pass).
     */
    public function canManageMarks(): bool
    {
        return $this->isOwner() && $this->review->allowsMarkManagement();
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
     * Marks on this pass and the one before it waiting for the human to
     * verify the agent's fixes.
     */
    public function awaitingVerificationMarks()
    {
        return $this->review->screenshots
            ->concat($this->review->parent?->screenshots ?? collect())
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

        $pin = $this->promoteFinding($finding, $asSeverity);
        $this->offerUndo('Added M'.$pin?->number, ['kind' => 'findings', 'findings' => [$finding->id], 'pins' => array_filter([$pin?->id])]);
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
        $this->offerUndo('Dismissed a hint', ['kind' => 'findings', 'findings' => [$finding->id], 'pins' => []]);
        $this->loadReview();
    }

    /**
     * Batch-accept open findings on the active screenshot.
     * $panel: "second" (non-guest) or "guest".
     */
    public function acceptOpenFindings(string $panel = 'all'): void
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
            ->filter(fn (Finding $finding) => match ($panel) { 'guest' => $finding->isGuest(), 'all' => true, default => ! $finding->isGuest() })
            ->values();

        $pins = $findings->map(fn (Finding $finding) => $this->promoteFinding($finding)?->id)->filter()->values()->all();

        if ($findings->isNotEmpty()) {
            $count = $findings->count();
            $this->offerUndo("Added {$count} ".($count === 1 ? 'mark' : 'marks'), ['kind' => 'findings', 'findings' => $findings->pluck('id')->all(), 'pins' => $pins]);
        }

        $this->loadReview();
    }

    /**
     * Batch-dismiss open findings on the active screenshot.
     * $panel: "second" (non-guest) or "guest".
     */
    public function dismissOpenFindings(string $panel = 'all'): void
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
            ->filter(fn (Finding $finding) => match ($panel) { 'guest' => $finding->isGuest(), 'all' => true, default => ! $finding->isGuest() })
            ->values();

        foreach ($findings as $finding) {
            $finding->update(['status' => Finding::STATUS_DISMISSED]);
        }

        if ($findings->isNotEmpty()) {
            $count = $findings->count();
            $this->offerUndo("Dismissed {$count} ".($count === 1 ? 'hint' : 'hints'), ['kind' => 'findings', 'findings' => $findings->pluck('id')->all(), 'pins' => []]);
        }

        $this->loadReview();
    }

    protected function promoteFinding(Finding $finding, ?string $asSeverity = null): ?Annotation
    {
        if (! $finding->isOpen()) {
            return null;
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

        return $pin;
    }

    public function refreshSecondOpinion(SecondOpinionService $opinions): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $opinions->requestForReview($this->review, $this->activeScreenshotIndex);
        $this->loadReview();
    }

    /**
     * The owner deletes this review now instead of waiting for it to expire:
     * every pass, with its screenshots, marks and comments. The link stops
     * working at once.
     */
    public function deleteReview(ReviewService $reviews): void
    {
        if (! $this->isOwner()) {
            return;
        }

        $count = $reviews->deleteLoop($this->review);

        session()->flash('status', $count === 1 ? 'Review deleted, with its screenshots.' : "Review deleted, with all {$count} passes and their screenshots.");

        $this->redirect('/reviews');
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
    class="rm-desk flex h-svh max-h-svh flex-col overflow-hidden"
    @if ($this->opinionPending)
        wire:poll.visible.3s="loadReview"
    @else
        wire:poll.visible.30s="loadReview"
    @endif
>
    {{-- Koati's shell: the work sits on an inset panel over the desk. --}}
    <div class="rm-shell">
    @include('review.partials.header')

    @if ($mode === 'guest' && ! $review->allowsGuestAccess())
        <div class="mx-auto flex w-full max-w-sm flex-1 flex-col items-center justify-center px-4 py-16 text-center sm:px-6">
            <div class="hatch flex size-14 items-center justify-center rounded-2xl text-zinc-300">
                <flux:icon.lock-closed class="size-6 text-zinc-500" />
            </div>
            <h2 class="mt-5 text-lg font-semibold text-zinc-900">This guest link has expired</h2>
            <p class="mt-1.5 text-sm text-muted-foreground">Ask whoever shared it for a new one.</p>
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
    {{-- Koati's way: act at once, offer Undo. A decision waits a few seconds
         before it reaches the agent, so it can be taken back too. --}}
    @if ($this->isOwner())
        <div
            x-data="{
                message: '',
                pending: null,
                left: 0,
                timer: null,
                show(message) {
                    this.clear();
                    this.message = message;
                    this.timer = setTimeout(() => this.clear(), 12000);
                },
                decide(kind) {
                    this.clear();
                    this.pending = kind;
                    this.left = 8;
                    this.timer = setInterval(() => { if (--this.left <= 0) this.commit(); }, 1000);
                },
                commit() {
                    const kind = this.pending;
                    this.clear();
                    kind === 'approve' ? $wire.approve() : $wire.requestChanges();
                },
                undo() {
                    const deciding = this.pending;
                    this.clear();
                    if (! deciding) $wire.undo();
                },
                {{-- Keys, Linear-style: A approves, C asks for changes (both keep
                     the undo window), J/K step through marks. Never while typing. --}}
                marks: @js($this->activeMarks->sortBy('number')->pluck('id')->values()),
                key(e) {
                    if (e.metaKey || e.ctrlKey || e.altKey || e.target.closest('input, textarea, select, [contenteditable]')) return;
                    const open = @js($review->isOpenForFeedback());
                    if (open && e.key === 'a') { e.preventDefault(); this.decide('approve'); }
                    else if (open && e.key === 'c') { e.preventDefault(); this.decide('changes'); }
                    else if ((e.key === 'j' || e.key === 'k') && this.marks.length) {
                        e.preventDefault();
                        const at = this.marks.indexOf($store.rmFocus?.mark);
                        const next = e.key === 'j' ? Math.min(at + 1, this.marks.length - 1) : Math.max(at - 1, 0);
                        $store.rmFocus.mark = this.marks[at === -1 ? 0 : next];
                    }
                },
                clear() {
                    clearTimeout(this.timer);
                    clearInterval(this.timer);
                    this.timer = null;
                    this.message = '';
                    this.pending = null;
                },
            }"
            x-on:undoable.window="show($event.detail.message)"
            x-on:rm-decide.window="decide($event.detail.kind)"
            x-on:keydown.window="key($event)"
            x-on:beforeunload.window="if (pending) { $event.preventDefault(); $event.returnValue = ''; }"
            x-show="message || pending"
            x-cloak
            x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]"
            x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:leave="transition duration-150"
            x-transition:leave-end="opacity-0"
            class="pointer-events-none fixed inset-x-0 bottom-24 z-[70] flex justify-center px-4 md:bottom-6"
            role="status"
            aria-live="polite"
        >
            <div class="pointer-events-auto flex items-center gap-1 rounded-full bg-zinc-900 py-1.5 pl-4 pr-1.5 text-sm text-white shadow-lg shadow-black/20">
                <span x-show="! pending" x-text="message"></span>
                <span x-show="pending" class="tabular-nums" x-text="(pending === 'approve' ? 'Approving' : 'Sending changes to the agent') + ' in ' + left + 's'"></span>
                <button type="button" class="ml-2 rounded-full px-3 py-1 font-medium text-key transition-colors night:text-white hover:bg-white/10" x-on:click="undo()">Undo</button>
                <button type="button" x-show="pending" class="rounded-full bg-white/10 px-3 py-1 font-medium transition-colors hover:bg-white/20" x-on:click="commit()">Send now</button>
            </div>
        </div>
    @endif
</div>
</div>
