<?php

namespace App\Models;

use App\Services\ScreenshotStorage;
use App\Support\MarkFocus;
use App\Support\TasteLenses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Review extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXPIRED = 'expired';

    /** Default lifetime for guest share links (aligned with review expires_at). */
    public const SHARE_EXPIRY_DAYS = 7;

    public const TYPE_UI = 'ui';

    public const TYPE_WEBSITE = 'website';

    public const TYPE_PRESENTATION = 'presentation';

    public const TYPE_EMAIL = 'email';

    public const SOURCE_IMAGE = 'image';

    public const SOURCE_URL = 'url';

    public const SOURCE_PDF = 'pdf';

    public const SOURCE_HTML = 'html';

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'public_id',
        'token',
        'share_token',
        'title',
        'context',
        'type',
        'page_url',
        'webhook_url',
        'dom_path',
        'pass',
        'status',
        'decision_note',
        'decision_at',
        'expires_at',
        'share_expires_at',
        'comments_enabled',
    ];

    /**
     * Never let the secret capability tokens leak through serialization.
     * webhook_url stays hidden too — callback URLs often embed secrets.
     */
    protected $hidden = [
        'token',
        'share_token',
        'webhook_url',
    ];

    protected function casts(): array
    {
        return [
            'decision_at' => 'datetime',
            'expires_at' => 'datetime',
            'share_expires_at' => 'datetime',
            'comments_enabled' => 'boolean',
            'pass' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Review $review): void {
            $review->public_id ??= (string) Str::ulid();
            $review->token ??= Str::random(40);
            $review->share_token ??= Str::random(40);
            $review->expires_at ??= now()->addDays(7);
            $review->share_expires_at ??= now()->addDays(self::SHARE_EXPIRY_DAYS)->endOfDay();
            $review->status ??= self::STATUS_PENDING;
            $review->pass ??= 1;
            $review->type ??= self::TYPE_UI;
        });
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::TYPE_UI, self::TYPE_WEBSITE, self::TYPE_PRESENTATION, self::TYPE_EMAIL];
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_WEBSITE => 'Website',
            self::TYPE_PRESENTATION => 'Slide',
            self::TYPE_EMAIL => 'Email',
            default => 'UI',
        };
    }

    /**
     * One-line reviewing hint shown to the human, tuned per content type.
     */
    public function typeGuidance(): string
    {
        return match ($this->type) {
            self::TYPE_WEBSITE => 'Reviewing a website — check the above-the-fold story, navigation clarity, and how it holds up across viewports.',
            self::TYPE_PRESENTATION => 'Reviewing slides — check one idea per slide, text density, and consistency across the deck.',
            self::TYPE_EMAIL => 'Reviewing an email — check subject/preheader, one dominant CTA, dark-mode colors, and the footer/unsubscribe.',
            default => 'Reviewing a UI — check hierarchy, spacing rhythm, contrast, and interactive affordances.',
        };
    }

    /**
     * The kind of input this review was created from, derived from the first
     * source screenshot's capture metadata. Plain uploads carry no meta, so
     * they (and legacy reviews) resolve to image.
     */
    public function sourceKind(): string
    {
        $origin = $this->screenshots->first()?->meta['origin'] ?? null;

        return match ($origin) {
            'capture' => self::SOURCE_URL,
            'pdf' => self::SOURCE_PDF,
            'html' => self::SOURCE_HTML,
            default => self::SOURCE_IMAGE,
        };
    }

    public function sourceKindLabel(): string
    {
        return match ($this->sourceKind()) {
            self::SOURCE_URL => 'URL',
            self::SOURCE_PDF => 'PDF',
            self::SOURCE_HTML => 'HTML',
            default => 'Image',
        };
    }

    /**
     * When the source was captured — a URL snapshot is frozen at this moment.
     */
    public function capturedAt(): ?Carbon
    {
        return $this->screenshots->first()?->created_at;
    }

    public function sourceDomain(): ?string
    {
        $url = $this->page_url ?? $this->screenshots->first()?->meta['page_url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: null;
    }

    /**
     * Rendered-DOM snapshot stored at capture time. AI context only — never
     * surfaced to reviewers.
     */
    public function domHtml(): ?string
    {
        if (! $this->dom_path) {
            return null;
        }

        try {
            return Storage::disk(ScreenshotStorage::diskName())->get($this->dom_path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The reviewable screenshots. "After" evidence shots attached to resolved
     * marks are excluded — they are reachable only via Annotation::afterScreenshot()
     * so the 5-shot cap, payload indexes, and second-opinion loops stay intact.
     */
    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class)
            ->where('kind', Screenshot::KIND_SOURCE)
            ->orderBy('sort_order');
    }

    /**
     * Marks across every source screenshot. HasManyThrough builds its own query
     * on Screenshot, so it does not inherit the kind filter from screenshots()
     * — it is repeated here or "after" evidence shots would leak into
     * the mark counts and verifyResolvedForReview().
     */
    public function annotations(): HasManyThrough
    {
        return $this->hasManyThrough(Annotation::class, Screenshot::class)
            ->where('screenshots.kind', Screenshot::KIND_SOURCE);
    }

    /**
     * Next M-number for this review. Shared across all shots so M1 can't
     * appear on both shot 1 and shot 2 of the same pass.
     */
    public function nextMarkNumber(): int
    {
        $max = Annotation::query()
            ->whereIn('screenshot_id', $this->screenshots()->select('screenshots.id'))
            ->max('number');

        return ((int) $max) + 1;
    }

    /**
     * Stable 1-based S# / G# for open suggestions across every shot.
     * Second opinions and guests stay separate sequences.
     *
     * @return array{s: array<int, int>, g: array<int, int>}
     */
    public function suggestionDisplayNumbers(): array
    {
        if ($this->relationLoaded('screenshots')) {
            $findings = $this->screenshots
                ->flatMap(function (Screenshot $shot) {
                    if ($shot->relationLoaded('findings')) {
                        return $shot->findings;
                    }

                    return $shot->findings()->get();
                })
                ->sortBy('id')
                ->values();
        } else {
            $findings = Finding::query()
                ->whereIn('screenshot_id', $this->screenshots()->select('screenshots.id'))
                ->orderBy('id')
                ->get();
        }

        $secondOpinion = [];
        $guest = [];
        $s = 0;
        $g = 0;

        foreach ($findings as $finding) {
            if (! $finding->isOpen()) {
                continue;
            }

            if ($finding->isGuest()) {
                $guest[$finding->id] = ++$g;
            } else {
                $secondOpinion[$finding->id] = ++$s;
            }
        }

        return ['s' => $secondOpinion, 'g' => $guest];
    }

    public function reviewUrl(): string
    {
        return url('/r/'.$this->token);
    }

    /**
     * Guest link: suggest-only access for teammates. Never grants owner controls.
     */
    public function shareUrl(): string
    {
        return url('/r/'.$this->share_token);
    }

    public function regenerateShareToken(): void
    {
        $this->update([
            'share_token' => Str::random(40),
            // Fresh link gets a fresh default window.
            'share_expires_at' => now()->addDays(self::SHARE_EXPIRY_DAYS)->endOfDay(),
        ]);
    }

    /**
     * Guest-link expiry is separate from the review's own expires_at.
     * Null means the link stays open until regenerated or the review expires.
     */
    public function isShareLinkExpired(): bool
    {
        return $this->share_expires_at !== null && $this->share_expires_at->isPast();
    }

    public function allowsGuestAccess(): bool
    {
        return ! $this->isShareLinkExpired() && ! $this->isExpired();
    }

    /**
     * Owner can turn commenting off without regenerating the guest link.
     */
    public function allowsComments(): bool
    {
        return (bool) $this->comments_enabled;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    /**
     * Human can still mark and decide only while waiting on their eye.
     */
    public function isOpenForFeedback(): bool
    {
        return $this->effectiveStatus() === self::STATUS_PENDING;
    }

    /**
     * The human can verify / reopen / move marks while waiting on the first
     * look and after requesting changes (so the agent's resolutions can be
     * checked next pass). Approved and expired reviews are frozen.
     */
    public function allowsMarkManagement(): bool
    {
        return in_array($this->effectiveStatus(), [self::STATUS_PENDING, self::STATUS_CHANGES_REQUESTED], true);
    }

    /**
     * Seconds an agent should wait between get_review polls while the human looks.
     */
    public const POLL_AFTER_SECONDS = 30;

    /**
     * What the agent should do next in the design checkup loop.
     *
     * @return array<string, mixed>
     */
    public function nextAction(): array
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_PENDING => [
                'action' => 'wait_for_human',
                'summary' => 'Share review_url with the human. Poll get_review about every '.self::POLL_AFTER_SECONDS.'s until they approve or request changes (compare updated_at to skip unchanged polls). Do not claim the UI is done.',
                'poll_after_seconds' => self::POLL_AFTER_SECONDS,
            ],
            self::STATUS_CHANGES_REQUESTED => $this->changesRequestedAction(),
            self::STATUS_APPROVED => $this->approvedAction(),
            self::STATUS_EXPIRED => [
                'action' => 'expired',
                'summary' => 'This review link expired. Start a fresh create_review if you still need a checkup.',
            ],
            default => [
                'action' => 'wait_for_human',
                'summary' => 'Poll get_review and follow the human decision.',
                'poll_after_seconds' => self::POLL_AFTER_SECONDS,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function changesRequestedAction(): array
    {
        $outstanding = $this->outstandingMarks();
        $own = $outstanding->where('carried_over', false);
        $carried = $outstanding->where('carried_over', true);
        $note = trim((string) $this->decision_note);
        $noteLine = $note !== '' ? ' The human also wrote: "'.$note.'" — apply that too.' : '';

        $nextPass = array_filter([
            'create_next_pass' => true,
            'parent_id' => $this->public_id,
            // capture_url passes need page_url; it is inherited, but saying so
            // saves the agent a failed call.
            'page_url' => $this->page_url,
        ], fn ($value) => $value !== null);

        // Changes requested with nothing marked: the note is the whole brief.
        // Saying "every mark is resolved" here sent agents straight to a new
        // pass with unchanged screenshots.
        if ($outstanding->isEmpty() && ! $this->annotations()->exists()) {
            return [
                'action' => 'apply_decision_note',
                'summary' => $note !== ''
                    ? 'The human requested changes without leaving marks. Their note: "'.$note.'". Apply it, then create_review with parent_id set to this review id and fresh screenshots of the fixed UI.'
                    : 'The human requested changes but left no marks and no note. Ask them in chat what to change before editing anything.',
                'decision_note' => $note !== '' ? $note : null,
            ] + $nextPass;
        }

        if ($outstanding->isEmpty()) {
            return [
                'action' => 'open_next_pass',
                'summary' => 'Every mark is resolved.'.$noteLine.' Open the next pass now: create_review with parent_id set to this review id and fresh screenshots of the fixed UI so the human can verify.',
            ] + $nextPass;
        }

        $summary = 'Apply work_packets.pins (human marks) in order: must-fix → nit. Honor keep (do not change). As you work each mark, call resolve_marks with its id — status "in_progress" while editing, "resolved" (with a short note) once fixed. Never set "verified"; that is the human\'s call. Treat second_opinion as hints.';

        if ($carried->isNotEmpty()) {
            $summary .= ' The human reopened '.$carried->count().' mark(s) from the previous pass ('.$this->markNumbers($carried).') — they are in work_packets.carried_over; fix and resolve those too.';
        }

        $unanswered = $outstanding->filter(
            fn (array $mark) => $mark['severity'] === Annotation::SEVERITY_QUESTION && $mark['question_answer'] === null,
        );

        if ($unanswered->isNotEmpty()) {
            $summary .= ' '.$this->markNumbers($unanswered).' '.($unanswered->count() === 1 ? 'is a question' : 'are questions').' with no answer yet: ask the human in chat to answer in the review, then get_review again — do not invent an answer.';
        }

        $summary .= $noteLine.' Once every mark is resolved, create_review with parent_id set to this review id, new screenshots of the fixed UI, and a fresh context for what to look at on this next pass.';

        return array_filter([
            'action' => 'apply_pins_then_next_pass',
            'summary' => $summary,
            'outstanding_marks' => $outstanding->count(),
            'carried_over_marks' => $carried->isNotEmpty() ? $carried->count() : null,
            'awaiting_answers' => $unanswered->isNotEmpty()
                ? $unanswered->map(fn (array $mark) => Arr::only($mark, ['id', 'number']))->values()->all()
                : null,
            'decision_note' => $note !== '' ? $note : null,
        ], fn ($value) => $value !== null) + $nextPass;
    }

    /**
     * @return array<string, mixed>
     */
    protected function approvedAction(): array
    {
        $open = $this->outstandingMarks();

        if ($open->isEmpty()) {
            return [
                'action' => 'done',
                'summary' => 'Human approved this pass. Stop editing unless they ask for another checkup.',
            ];
        }

        return [
            'action' => 'done',
            'summary' => 'Human approved this pass with '.$open->count().' mark(s) still open ('.$this->markNumbers($open).'). Mention them to the human, but do not fix them unless asked.',
            'open_marks' => $open->count(),
        ];
    }

    /**
     * Marks still needing agent work (open or in progress) on this pass, plus
     * previous-pass marks that are still open — typically ones the human
     * reopened while checking this pass. Plain arrays, not models: this runs
     * for every row in list_reviews.
     *
     * @return Collection<int, array{id: int, number: int, severity: string, question_answer: ?string, carried_over: bool}>
     */
    public function outstandingMarks(): Collection
    {
        $reviewIds = array_values(array_filter([$this->id, $this->parent_id]));

        return Annotation::query()
            ->join('screenshots', 'screenshots.id', '=', 'annotations.screenshot_id')
            ->whereIn('screenshots.review_id', $reviewIds)
            ->where('screenshots.kind', Screenshot::KIND_SOURCE)
            ->whereIn('annotations.status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS])
            ->orderBy('screenshots.review_id')
            ->orderBy('annotations.number')
            ->get(['annotations.id', 'annotations.number', 'annotations.severity', 'annotations.question_answer', 'screenshots.review_id'])
            ->map(fn (Annotation $mark) => [
                'id' => (int) $mark->id,
                'number' => (int) $mark->number,
                'severity' => (string) $mark->severity,
                'question_answer' => filled($mark->question_answer) ? (string) $mark->question_answer : null,
                'carried_over' => (int) $mark->review_id !== (int) $this->id,
            ])
            ->values();
    }

    /**
     * "M2, M5" — how the human refers to marks. Previous-pass marks are
     * prefixed with their pass so M2 (pass 1) and M2 (pass 2) stay distinct.
     *
     * @param  Collection<int, array{number: int, carried_over: bool}>  $marks
     */
    protected function markNumbers(Collection $marks): string
    {
        return $marks
            ->map(fn (array $mark) => 'M'.$mark['number'].($mark['carried_over'] ? ' (previous pass)' : ''))
            ->implode(', ');
    }

    /**
     * Structured work packet for agents: human marks first (API key: pins), second opinion as hints.
     *
     * @return array<string, mixed>
     */
    public function statusLabel(): string
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_PENDING => 'Waiting on your eye',
            self::STATUS_CHANGES_REQUESTED => 'Changes requested — apply marks, then open the next pass',
            self::STATUS_APPROVED => 'Looks good — approved',
            self::STATUS_EXPIRED => 'This review link expired',
            default => (string) $this->status,
        };
    }

    /**
     * Lightweight token-scoped memory for list_reviews (no full work packets).
     *
     * @return array<string, mixed>
     */
    public function toListSummary(): array
    {
        $this->loadMissing(['screenshots.annotations', 'parent']);

        $marks = $this->screenshots->flatMap->annotations;
        $outstanding = $marks->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS]);
        $awaiting = $marks->where('status', Annotation::STATUS_RESOLVED);
        $next = $this->nextAction();

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'type' => $this->type,
            'pass' => $this->pass,
            'parent_id' => $this->parent?->public_id,
            'status' => $this->effectiveStatus(),
            'status_label' => $this->statusLabel(),
            'next_action' => $next['action'],
            'next_action_summary' => $next['summary'],
            'review_url' => $this->reviewUrl(),
            'board_url' => $this->boardUrl(),
            'loop' => [
                'pass' => $this->pass,
                'must_fix_count' => $outstanding->where('severity', Annotation::SEVERITY_MUST_FIX)->count(),
                'nit_count' => $outstanding->where('severity', Annotation::SEVERITY_NIT)->count(),
                'question_count' => $outstanding->where('severity', Annotation::SEVERITY_QUESTION)->count(),
                'outstanding_count' => $outstanding->count(),
                'awaiting_verification_count' => $awaiting->count(),
                'verified_count' => $marks->where('status', Annotation::STATUS_VERIFIED)->count(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'decision_at' => $this->decision_at?->toIso8601String(),
        ];
    }

    /**
     * Multi-pass revision ledger from the root pass through this review.
     *
     * @return list<array<string, mixed>>
     */
    public function passLedger(): array
    {
        $chain = [];
        $cursor = $this;
        $visited = [];

        while ($cursor && ! isset($visited[$cursor->id])) {
            $visited[$cursor->id] = true;
            array_unshift($chain, $cursor);
            $cursor->loadMissing('parent');
            $cursor = $cursor->parent;
        }

        collect($chain)->each(fn (Review $review) => $review->loadMissing([
            'screenshots.annotations.afterScreenshot',
        ]));

        return collect($chain)->map(function (Review $review) {
            $marks = $review->screenshots->flatMap->annotations;

            return [
                'id' => $review->public_id,
                'pass' => $review->pass,
                'title' => $review->title,
                'status' => $review->effectiveStatus(),
                'status_label' => $review->statusLabel(),
                'decision_note' => $review->decision_note,
                'decision_at' => $review->decision_at?->toIso8601String(),
                'review_url' => $review->reviewUrl(),
                'is_current' => $review->is($this),
                'mark_count' => $marks->count(),
                'outstanding_count' => $marks->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS])->count(),
                'resolved_count' => $marks->where('status', Annotation::STATUS_RESOLVED)->count(),
                'verified_count' => $marks->where('status', Annotation::STATUS_VERIFIED)->count(),
                'after_evidence_count' => $marks->filter(fn (Annotation $mark) => $mark->after_screenshot_id !== null)->count(),
            ];
        })->values()->all();
    }

    public function toAgentPayload(): array
    {
        $this->load([
            'screenshots.findings',
            'screenshots.annotations' => fn ($query) => $query->withCount('comments')->with(['comments', 'afterScreenshot']),
            'parent.screenshots.annotations' => fn ($query) => $query->withCount('comments')->with(['comments', 'afterScreenshot']),
            'parent.parent',
        ]);

        // work_packets.pins, built as the screenshots are walked so each mark is
        // serialized once rather than once per place it appears.
        $allPins = [];

        $screenshots = $this->screenshots->map(function (Screenshot $shot, int $index) use (&$allPins) {
            $marks = $shot->annotations->sortBy('number')->values();

            $marks->each(function (Annotation $annotation) use ($shot) {
                if (! $annotation->relationLoaded('screenshot')) {
                    $annotation->setRelation('screenshot', $shot);
                }
            });

            $pins = $marks->map(fn (Annotation $annotation) => $this->markToArray($annotation, verbose: true))->all();

            $allPins = array_merge($allPins, array_map(
                fn (array $pin) => $pin + ['screenshot_index' => $index],
                $pins,
            ));

            $findings = $shot->findings
                ->filter(fn (Finding $finding) => $finding->isOpen() && ! $finding->isGuest())
                ->values()
                ->map(fn (Finding $finding) => $finding->toAgentArray())
                ->all();

            $resolved = $shot->findings
                ->filter(fn (Finding $finding) => ! $finding->isOpen() && ! $finding->isGuest())
                ->values()
                ->map(fn (Finding $finding) => $finding->toAgentArray())
                ->all();

            // Guest suggestions stay owner-only until accepted (then they arrive as pins).
            $guestOpenCount = $shot->findings
                ->filter(fn (Finding $finding) => $finding->isOpen() && $finding->isGuest())
                ->count();

            return [
                'index' => $index,
                'id' => $shot->id,
                'url' => $shot->url(),
                'width' => $shot->width,
                'height' => $shot->height,
                'meta' => $shot->meta,
                'second_opinion_status' => $shot->second_opinion_status,
                'pins' => $pins,
                'second_opinion' => $findings,
                'second_opinion_resolved' => $resolved,
                'guest_suggestion_count' => $guestOpenCount,
            ];
        })->all();

        $allFindings = collect($screenshots)->flatMap(fn (array $s) => collect($s['second_opinion'])->map(
            fn (array $f) => $f + ['screenshot_index' => $s['index']]
        ))->values()->all();

        $allResolved = collect($screenshots)->flatMap(fn (array $s) => collect($s['second_opinion_resolved'])->map(
            fn (array $f) => $f + ['screenshot_index' => $s['index']]
        ))->values()->all();

        $guestSuggestionCount = collect($screenshots)->sum('guest_suggestion_count');

        // Severity buckets are triage views of work_packets.pins, so they carry
        // the mark minus its comment thread — the agent already has the full
        // record above, keyed by the same id.
        $bucket = fn (Collection $pins) => $pins
            ->map(fn (array $pin) => Arr::except($pin, 'comments'))
            ->values()
            ->all();

        // The agent should only re-work marks it has not resolved yet. Keeps stay
        // fully listed (they are "leave this alone" reminders, not tasks).
        $outstanding = collect($allPins)->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS]);

        $mustFix = $bucket($outstanding->where('severity', Annotation::SEVERITY_MUST_FIX));
        $nits = $bucket($outstanding->where('severity', Annotation::SEVERITY_NIT));
        $questions = $bucket($outstanding->where('severity', Annotation::SEVERITY_QUESTION));
        $tweaks = $bucket($outstanding->whereIn('severity', Annotation::tweakSeverities()));
        $keeps = $bucket(collect($allPins)->where('severity', Annotation::SEVERITY_KEEP));

        $awaitingVerification = $bucket(collect($allPins)->where('status', Annotation::STATUS_RESOLVED));
        $verifiedCount = collect($allPins)->where('status', Annotation::STATUS_VERIFIED)->count();

        // Previous-pass marks that are open again (the human reopened them while
        // checking this pass). They are work for this pass too; without this
        // they only surfaced inside previous_pass and an approve left them open.
        $previousPass = $this->previousPassPayload();
        $carriedOver = $bucket(collect($previousPass['marks'] ?? [])
            ->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS]));

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'context' => $this->context,
            'type' => $this->type,
            'page_url' => $this->page_url,
            'pass' => $this->pass,
            'parent_id' => $this->parent?->public_id,
            'status' => $this->effectiveStatus(),
            'status_label' => $this->statusLabel(),
            'review_url' => $this->reviewUrl(),
            'board_url' => $this->boardUrl(),
            'guest_share_url' => $this->shareUrl(),
            'decision_note' => $this->decision_note,
            'decision_at' => $this->decision_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            // Changes whenever the human does anything — an unchanged value
            // means the poll can be skipped.
            'updated_at' => $this->lastActivityAt()?->toIso8601String(),
            'guidance' => 'Apply human marks first (work_packets.pins): must-fix, then nit. Honor keep (leave alone). When suggested_copy is set, prefer that exact string. When question_answer is set, treat the question as answered — do not invent a different answer. Read recent comments on each pin for context. Ask before inventing answers to unanswered question marks. Treat second_opinion as hints only until accepted (then they arrive as pins with source provenance).',
            'taste' => TasteLenses::forType($this->type),
            'next_action' => $this->nextAction(),
            'loop' => [
                'pass' => $this->pass,
                'parent_id' => $this->parent?->public_id,
                'must_fix_count' => count($mustFix),
                'nit_count' => count($nits),
                'question_count' => count($questions),
                'keep_count' => count($keeps),
                'tweak_count' => count($tweaks),
                'second_opinion_count' => count($allFindings),
                'second_opinion_accepted_count' => collect($allResolved)->where('status', Finding::STATUS_ACCEPTED)->count(),
                'second_opinion_dismissed_count' => collect($allResolved)->where('status', Finding::STATUS_DISMISSED)->count(),
                'guest_suggestion_count' => $guestSuggestionCount,
                'outstanding_count' => $outstanding->count() + count($carriedOver),
                'carried_over_count' => count($carriedOver),
                'resolved_count' => count($awaitingVerification),
                'awaiting_verification_count' => count($awaitingVerification),
                'verified_count' => $verifiedCount,
            ],
            'work_packets' => [
                'pins' => $allPins,
                'must_fix' => $mustFix,
                'nits' => $nits,
                'questions' => $questions,
                'keeps' => $keeps,
                'tweaks' => $tweaks,
                'awaiting_verification' => $awaitingVerification,
                'carried_over' => $carriedOver,
                'second_opinion' => $allFindings,
                'second_opinion_resolved' => $allResolved,
            ],
            'pass_ledger' => $this->passLedger(),
            'previous_pass' => $previousPass,
            'screenshots' => $screenshots,
        ];
    }

    /**
     * Latest human or agent activity on this pass: the review row, its marks,
     * comments, findings, and the parent's marks (reopened on this pass).
     * Expects toAgentPayload()'s relations to be loaded.
     */
    protected function lastActivityAt(): ?Carbon
    {
        $marks = $this->screenshots->flatMap->annotations
            ->merge($this->parent?->screenshots->flatMap->annotations ?? []);

        return collect([$this->updated_at])
            ->merge($marks->pluck('updated_at'))
            ->merge($marks->flatMap->comments->pluck('created_at'))
            ->merge($this->screenshots->flatMap->findings->pluck('updated_at'))
            ->filter()
            ->max();
    }

    /**
     * Serialize one mark (human annotation) for the agent work packet.
     *
     * Every mark appears several times in a payload — under its screenshot, in
     * work_packets.pins, and again in a severity bucket — so comment bodies
     * ship on the canonical screenshots[].pins copy only. Agents read the
     * buckets to decide what to fix and follow the id back for the thread.
     *
     * @return array<string, mixed>
     */
    protected function markToArray(Annotation $annotation, bool $verbose = false): array
    {
        $comments = $verbose
            ? ($annotation->relationLoaded('comments') ? $annotation->comments : $annotation->comments()->get())
            : collect();

        return [
            'id' => $annotation->id,
            'number' => $annotation->number,
            'x' => (float) $annotation->x,
            'y' => (float) $annotation->y,
            'area' => $annotation->region(),
            'severity' => $annotation->severity,
            'body' => $annotation->body,
            'suggested_copy' => $annotation->suggested_copy,
            'question_answer' => $annotation->question_answer,
            'source' => $annotation->source ?: Annotation::SOURCE_HUMAN,
            'source_label' => $annotation->sourceLabel(),
            'promoted_from_finding_id' => $annotation->promoted_from_finding_id,
            'status' => $annotation->status,
            'resolution_note' => $annotation->resolution_note,
            'after_screenshot_url' => $annotation->afterScreenshot?->url(),
            'comment_count' => (int) ($annotation->comments_count ?? $annotation->comments()->count()),
            // Geometry only. The inline app pairs this with the screenshot url
            // it already has rather than carrying a signed URL on every copy of
            // every mark; server-rendered previews call MarkFocus directly.
            'focus_preview' => Arr::except(MarkFocus::forMark($annotation), 'bg_style'),
        ] + ($verbose ? [
            'comments' => $comments
                ->take(-10)
                ->values()
                ->map(fn (AnnotationComment $comment) => [
                    'id' => $comment->id,
                    'author' => $comment->author,
                    'from_owner' => (bool) $comment->from_owner,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toIso8601String(),
                ])
                ->all(),
        ] : []);
    }

    /**
     * Marks carried over from the parent pass, so the agent can see what the
     * human has since verified or reopened after the last round of fixes.
     *
     * @return array<string, mixed>|null
     */
    protected function previousPassPayload(): ?array
    {
        $parent = $this->parent;

        if (! $parent) {
            return null;
        }

        $marks = $parent->screenshots
            ->flatMap(function (Screenshot $shot, int $index) {
                return $shot->annotations->map(function (Annotation $annotation) use ($shot, $index) {
                    if (! $annotation->relationLoaded('screenshot')) {
                        $annotation->setRelation('screenshot', $shot);
                    }

                    return [$annotation, $index];
                });
            })
            ->sortBy(fn (array $pair) => $pair[0]->number)
            ->values()
            ->map(fn (array $pair) => $this->markToArray($pair[0]) + ['screenshot_index' => $pair[1]]);

        return [
            'id' => $parent->public_id,
            'pass' => $parent->pass,
            'review_url' => $parent->reviewUrl(),
            // Just enough to render a parent-pass mark crop: one signed URL per
            // shot instead of one embedded in every mark.
            'screenshots' => $parent->screenshots->values()->map(fn (Screenshot $shot, int $index) => [
                'index' => $index,
                'url' => $shot->url(),
            ])->all(),
            'marks' => $marks->all(),
            'outstanding_count' => $marks->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS])->count(),
            'resolved_count' => $marks->where('status', Annotation::STATUS_RESOLVED)->count(),
            'verified_count' => $marks->where('status', Annotation::STATUS_VERIFIED)->count(),
        ];
    }

    public function boardUrl(): string
    {
        return url('/r/'.$this->token.'/board');
    }
}
