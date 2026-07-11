<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Review extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'public_id',
        'token',
        'share_token',
        'title',
        'context',
        'page_url',
        'pass',
        'status',
        'decision_note',
        'decision_at',
        'expires_at',
    ];

    /**
     * Never let the secret capability tokens leak through serialization.
     */
    protected $hidden = [
        'token',
        'share_token',
    ];

    protected function casts(): array
    {
        return [
            'decision_at' => 'datetime',
            'expires_at' => 'datetime',
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
            $review->status ??= self::STATUS_PENDING;
            $review->pass ??= 1;
        });
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

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class)->orderBy('sort_order');
    }

    public function annotations(): HasManyThrough
    {
        return $this->hasManyThrough(Annotation::class, Screenshot::class);
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
        $this->update(['share_token' => Str::random(40)]);
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
            self::STATUS_CHANGES_REQUESTED => [
                'action' => 'apply_pins_then_next_pass',
                'summary' => 'Apply work_packets.pins (human marks) in order: must-fix → wording/spacing/size/color/alignment → nit. Honor keep (do not change). Resolve question with the human before inventing a fix. Treat second_opinion as hints. Then create_review with parent_id set to this review id and new screenshots of the fixed UI.',
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
        $this->loadMissing(['screenshots.annotations', 'screenshots.findings', 'parent']);

        $screenshots = $this->screenshots->map(function (Screenshot $shot, int $index) {
            $pins = $shot->annotations
                ->sortBy('number')
                ->values()
                ->map(fn (Annotation $annotation) => [
                    'number' => $annotation->number,
                    'x' => (float) $annotation->x,
                    'y' => (float) $annotation->y,
                    'area' => $annotation->region(),
                    'severity' => $annotation->severity,
                    'body' => $annotation->body,
                ])->all();

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
                'url' => $shot->url(),
                'width' => $shot->width,
                'height' => $shot->height,
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

        $mustFix = collect($allPins)->where('severity', Annotation::SEVERITY_MUST_FIX)->values()->all();
        $nits = collect($allPins)->where('severity', Annotation::SEVERITY_NIT)->values()->all();
        $questions = collect($allPins)->where('severity', Annotation::SEVERITY_QUESTION)->values()->all();
        $keeps = collect($allPins)->where('severity', Annotation::SEVERITY_KEEP)->values()->all();
        $tweaks = collect($allPins)->whereIn('severity', Annotation::tweakSeverities())->values()->all();

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'context' => $this->context,
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
            'guest_share_url' => $this->shareUrl(),
            'decision_note' => $this->decision_note,
            'decision_at' => $this->decision_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'guidance' => 'Apply human marks first (work_packets.pins): must-fix, then tweaks (wording/spacing/size/color/alignment), then nit. Honor keep (leave alone). Ask before inventing answers to question marks. Treat second_opinion as hints only.',
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
            ],
            'work_packets' => [
                'pins' => $allPins,
                'must_fix' => $mustFix,
                'nits' => $nits,
                'questions' => $questions,
                'keeps' => $keeps,
                'tweaks' => $tweaks,
                'second_opinion' => $allFindings,
                'second_opinion_resolved' => $allResolved,
            ],
            'screenshots' => $screenshots,
        ];
    }
}
