<?php

namespace App\Models;

use App\Services\ScreenshotStorage;
use App\Support\MarkFocus;
use App\Support\TasteLenses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
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

    public function annotations(): HasManyThrough
    {
        return $this->hasManyThrough(Annotation::class, Screenshot::class);
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
     * What the agent should do next in the design checkup loop.
     *
     * @return array{action: string, summary: string, create_next_pass?: bool, parent_id?: string}
     */
    public function nextAction(): array
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_PENDING => [
                'action' => 'wait_for_human',
                'summary' => 'Share review_url with the human. Poll get_review until they approve or request changes. Do not claim the UI is done.',
            ],
            self::STATUS_CHANGES_REQUESTED => $this->outstandingMarkCount() > 0
                ? [
                    'action' => 'apply_pins_then_next_pass',
                    'summary' => 'Apply work_packets.pins (human marks) in order: must-fix → nit. Honor keep (do not change). Resolve question with the human before inventing a fix. As you work each mark, call resolve_marks with its id — status "in_progress" while editing, "resolved" (with a short note) once fixed. Never set "verified"; that is the human\'s call. Treat second_opinion as hints. Once every mark is resolved, create_review with parent_id set to this review id, new screenshots of the fixed UI, and a fresh context for what to look at on this next pass.',
                    'create_next_pass' => true,
                    'parent_id' => $this->public_id,
                    'outstanding_marks' => $this->outstandingMarkCount(),
                ]
                : [
                    'action' => 'open_next_pass',
                    'summary' => 'Every mark is resolved. Open the next pass now: create_review with parent_id set to this review id and fresh screenshots of the fixed UI so the human can verify.',
                    'create_next_pass' => true,
                    'parent_id' => $this->public_id,
                ],
            self::STATUS_APPROVED => [
                'action' => 'done',
                'summary' => 'Human approved this pass. Stop editing unless they ask for another checkup.',
            ],
            self::STATUS_EXPIRED => [
                'action' => 'expired',
                'summary' => 'This review link expired. Start a fresh create_review if you still need a checkup.',
            ],
            default => [
                'action' => 'wait_for_human',
                'summary' => 'Poll get_review and follow the human decision.',
            ],
        };
    }

    /**
     * Structured work packet for agents: human marks first (API key: pins), second opinion as hints.
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(): array
    {
        $this->load([
            'screenshots.findings',
            'screenshots.annotations' => fn ($query) => $query->withCount('comments')->with('afterScreenshot'),
            'parent.screenshots.annotations' => fn ($query) => $query->withCount('comments')->with('afterScreenshot'),
        ]);

        $screenshots = $this->screenshots->map(function (Screenshot $shot, int $index) {
            $pins = $shot->annotations
                ->sortBy('number')
                ->values()
                ->map(function (Annotation $annotation) use ($shot) {
                    if (! $annotation->relationLoaded('screenshot')) {
                        $annotation->setRelation('screenshot', $shot);
                    }

                    return $this->markToArray($annotation);
                })
                ->all();

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
                'annotations' => $pins,
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

        $allPins = collect($screenshots)->flatMap(fn (array $s) => collect($s['pins'])->map(
            fn (array $p) => $p + ['screenshot_index' => $s['index']]
        ))->values()->all();

        // The agent should only re-work marks it has not resolved yet. Keeps stay
        // fully listed (they are "leave this alone" reminders, not tasks).
        $outstanding = collect($allPins)->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS]);

        $mustFix = $outstanding->where('severity', Annotation::SEVERITY_MUST_FIX)->values()->all();
        $nits = $outstanding->where('severity', Annotation::SEVERITY_NIT)->values()->all();
        $questions = $outstanding->where('severity', Annotation::SEVERITY_QUESTION)->values()->all();
        $tweaks = $outstanding->whereIn('severity', Annotation::tweakSeverities())->values()->all();
        $keeps = collect($allPins)->where('severity', Annotation::SEVERITY_KEEP)->values()->all();

        $awaitingVerification = collect($allPins)->where('status', Annotation::STATUS_RESOLVED)->values()->all();
        $verifiedCount = collect($allPins)->where('status', Annotation::STATUS_VERIFIED)->count();

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'context' => $this->context,
            'type' => $this->type,
            'page_url' => $this->page_url,
            'pass' => $this->pass,
            'parent_id' => $this->parent?->public_id,
            'status' => $this->effectiveStatus(),
            'status_label' => match ($this->effectiveStatus()) {
                self::STATUS_PENDING => 'Waiting on your eye',
                self::STATUS_CHANGES_REQUESTED => 'Changes requested — apply marks, then open the next pass',
                self::STATUS_APPROVED => 'Looks good — approved',
                self::STATUS_EXPIRED => 'This review link expired',
                default => $this->status,
            },
            'review_url' => $this->reviewUrl(),
            'board_url' => $this->boardUrl(),
            'guest_share_url' => $this->shareUrl(),
            'decision_note' => $this->decision_note,
            'decision_at' => $this->decision_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'guidance' => 'Apply human marks first (work_packets.pins): must-fix, then nit. Honor keep (leave alone). Ask before inventing answers to question marks. Treat second_opinion as hints only.',
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
                'outstanding_count' => $outstanding->count(),
                'resolved_count' => count($awaitingVerification),
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
                'second_opinion' => $allFindings,
                'second_opinion_resolved' => $allResolved,
            ],
            'previous_pass' => $this->previousPassPayload(),
            'screenshots' => $screenshots,
        ];
    }

    /**
     * Serialize one mark (human annotation) for the agent work packet.
     *
     * @return array<string, mixed>
     */
    protected function markToArray(Annotation $annotation): array
    {
        $focus = MarkFocus::forMark($annotation);

        return [
            'id' => $annotation->id,
            'number' => $annotation->number,
            'x' => (float) $annotation->x,
            'y' => (float) $annotation->y,
            'area' => $annotation->region(),
            'severity' => $annotation->severity,
            'body' => $annotation->body,
            'status' => $annotation->status,
            'resolution_note' => $annotation->resolution_note,
            'after_screenshot_url' => $annotation->afterScreenshot?->url(),
            'comment_count' => (int) ($annotation->comments_count ?? $annotation->comments()->count()),
            'focus_preview' => [
                'window' => $focus['window'],
                'overlay' => $focus['overlay'],
                'point' => $focus['point'],
                'ratio' => $focus['ratio'],
                'bg_style' => $focus['bg_style'],
            ],
        ];
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
            ->flatMap(function (Screenshot $shot) {
                return $shot->annotations->each(function (Annotation $annotation) use ($shot) {
                    if (! $annotation->relationLoaded('screenshot')) {
                        $annotation->setRelation('screenshot', $shot);
                    }
                });
            })
            ->sortBy('number')
            ->values()
            ->map(fn (Annotation $annotation) => $this->markToArray($annotation));

        return [
            'id' => $parent->public_id,
            'pass' => $parent->pass,
            'review_url' => $parent->reviewUrl(),
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

    /**
     * Marks still needing agent work (open or in progress), across all passes' screenshots.
     */
    public function outstandingMarkCount(): int
    {
        return $this->annotations()
            ->whereIn('status', [Annotation::STATUS_OPEN, Annotation::STATUS_IN_PROGRESS])
            ->count();
    }
}
