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
        'public_id',
        'token',
        'title',
        'context',
        'page_url',
        'status',
        'decision_note',
        'decision_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'decision_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Review $review): void {
            $review->public_id ??= (string) Str::ulid();
            $review->token ??= Str::random(40);
            $review->expires_at ??= now()->addDays(7);
            $review->status ??= self::STATUS_PENDING;
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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

    public function isOpenForFeedback(): bool
    {
        return in_array($this->effectiveStatus(), [self::STATUS_PENDING, self::STATUS_CHANGES_REQUESTED], true);
    }

    /**
     * Structured work packet for agents: human pins first, second opinion as hints.
     *
     * @return array<string, mixed>
     */
    public function toAgentPayload(): array
    {
        $this->loadMissing(['screenshots.annotations', 'screenshots.findings']);

        $screenshots = $this->screenshots->map(function (Screenshot $shot, int $index) {
            $pins = $shot->annotations
                ->sortBy('number')
                ->values()
                ->map(fn (Annotation $annotation) => [
                    'number' => $annotation->number,
                    'x' => (float) $annotation->x,
                    'y' => (float) $annotation->y,
                    'severity' => $annotation->severity,
                    'body' => $annotation->body,
                ])->all();

            $findings = $shot->findings
                ->values()
                ->map(fn (Finding $finding) => $finding->toAgentArray())
                ->all();

            return [
                'index' => $index,
                'url' => $shot->url(),
                'width' => $shot->width,
                'height' => $shot->height,
                'second_opinion_status' => $shot->second_opinion_status,
                'pins' => $pins,
                // Legacy key for older agents
                'annotations' => $pins,
                'second_opinion' => $findings,
            ];
        })->all();

        $allFindings = collect($screenshots)->flatMap(fn (array $s) => collect($s['second_opinion'])->map(
            fn (array $f) => $f + ['screenshot_index' => $s['index']]
        ))->values()->all();

        $allPins = collect($screenshots)->flatMap(fn (array $s) => collect($s['pins'])->map(
            fn (array $p) => $p + ['screenshot_index' => $s['index']]
        ))->values()->all();

        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'context' => $this->context,
            'page_url' => $this->page_url,
            'status' => $this->effectiveStatus(),
            'status_label' => match ($this->effectiveStatus()) {
                self::STATUS_PENDING => 'Waiting on your eye',
                self::STATUS_CHANGES_REQUESTED => 'Changes requested',
                self::STATUS_APPROVED => 'Looks good — approved',
                self::STATUS_EXPIRED => 'This review link expired',
                default => $this->status,
            },
            'review_url' => $this->reviewUrl(),
            'decision_note' => $this->decision_note,
            'decision_at' => $this->decision_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'guidance' => 'Apply human pins first (must-fix / nit). Treat second_opinion findings as hints only — never override the human decision.',
            'work_packets' => [
                'pins' => $allPins,
                'second_opinion' => $allFindings,
            ],
            'screenshots' => $screenshots,
        ];
    }
}
