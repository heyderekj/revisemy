<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Annotation extends Model
{
    public const SEVERITY_MUST_FIX = 'must-fix';

    public const SEVERITY_NIT = 'nit';

    public const SEVERITY_QUESTION = 'question';

    public const SEVERITY_KEEP = 'keep';

    /** @deprecated Kept for legacy marks; not offered in the composer. */
    public const SEVERITY_WORDING = 'wording';

    /** @deprecated Kept for legacy marks; not offered in the composer. */
    public const SEVERITY_SPACING = 'spacing';

    /** @deprecated Kept for legacy marks; not offered in the composer. */
    public const SEVERITY_SIZE = 'size';

    /** @deprecated Kept for legacy marks; not offered in the composer. */
    public const SEVERITY_COLOR = 'color';

    /** @deprecated Kept for legacy marks; not offered in the composer. */
    public const SEVERITY_ALIGNMENT = 'alignment';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_VERIFIED = 'verified';

    /**
     * @return list<string>
     */
    public static function severities(): array
    {
        return array_keys(self::severityLabels());
    }

    /**
     * Lifecycle statuses in board / progression order.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_keys(self::statusLabels());
    }

    /**
     * Human-facing labels for the lifecycle status.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_VERIFIED => 'Verified',
        ];
    }

    /**
     * Statuses an agent may set via resolve_marks. Verify and reopen stay human-only.
     *
     * @return list<string>
     */
    public static function agentStatuses(): array
    {
        return [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED];
    }

    /**
     * Human-facing labels for the mark form and sidebar.
     *
     * @return array<string, string>
     */
    public static function severityLabels(): array
    {
        return [
            self::SEVERITY_MUST_FIX => 'Must fix',
            self::SEVERITY_NIT => 'Nice to have',
            self::SEVERITY_QUESTION => 'Question',
            self::SEVERITY_KEEP => 'Keep this',
        ];
    }

    /**
     * Labels for display, including legacy tweak categories.
     *
     * @return array<string, string>
     */
    public static function allSeverityLabels(): array
    {
        return self::severityLabels() + [
            self::SEVERITY_WORDING => 'Wording',
            self::SEVERITY_SPACING => 'Spacing',
            self::SEVERITY_SIZE => 'Size',
            self::SEVERITY_COLOR => 'Color',
            self::SEVERITY_ALIGNMENT => 'Alignment',
        ];
    }

    /**
     * Legacy tweak-type marks (scoped visual/copy changes).
     *
     * @return list<string>
     */
    public static function tweakSeverities(): array
    {
        return [
            self::SEVERITY_WORDING,
            self::SEVERITY_SPACING,
            self::SEVERITY_SIZE,
            self::SEVERITY_COLOR,
            self::SEVERITY_ALIGNMENT,
        ];
    }

    public function label(): string
    {
        return self::allSeverityLabels()[$this->severity] ?? (string) $this->severity;
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    /**
     * Still needs the agent's attention (not yet resolved or verified).
     */
    public function isOutstanding(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
    }

    /**
     * Agent says done; waiting on the human to verify or reopen.
     */
    public function awaitsVerification(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Owner may mark resolved from the board without waiting on the agent.
     */
    public function canOwnerResolve(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
    }

    /**
     * Board column ownership, drop affordances, and header icons for the owner UI.
     *
     * @return array<string, array{owner: string, droppable: bool, empty: string, icon: string, icon_bg: string, icon_class: string}>
     */
    public static function boardColumnMeta(): array
    {
        return [
            self::STATUS_OPEN => [
                'owner' => 'You',
                'droppable' => true,
                'empty' => 'Drop to reopen',
                'icon' => 'flag',
                'icon_bg' => 'bg-zinc-100',
                'icon_class' => 'text-zinc-600',
            ],
            self::STATUS_IN_PROGRESS => [
                'owner' => 'Agent',
                'droppable' => false,
                'empty' => 'Agent starts fixes here',
                'icon' => 'cpu-chip',
                'icon_bg' => 'bg-zinc-100',
                'icon_class' => 'text-zinc-600',
            ],
            self::STATUS_RESOLVED => [
                'owner' => 'You or agent',
                'droppable' => true,
                'empty' => 'Drop to mark resolved',
                'icon' => 'check-circle',
                'icon_bg' => 'bg-zinc-100',
                'icon_class' => 'text-zinc-600',
            ],
            self::STATUS_VERIFIED => [
                'owner' => 'You',
                'droppable' => true,
                'empty' => 'Drop to verify',
                'icon' => 'shield-check',
                'icon_bg' => 'bg-zinc-100',
                'icon_class' => 'text-zinc-600',
            ],
        ];
    }

    /**
     * Tailwind classes for the small status badge in the sidebar and board.
     */
    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'bg-sky-100 text-sky-800',
            self::STATUS_RESOLVED => 'bg-amber-100 text-amber-800',
            self::STATUS_VERIFIED => 'bg-emerald-100 text-emerald-800',
            default => 'bg-zinc-100 text-zinc-600',
        };
    }

    /**
     * Tailwind classes for the numbered mark marker.
     *
     * Your own marks carry the accent yellow, which is why the ink foreground ships
     * with the fill — white on yellow is unreadable. Guest marks are styled gray at
     * the call site so the two never compete.
     */
    public function markerClass(): string
    {
        return 'bg-accent text-ink';
    }

    /**
     * Accent color for radio inputs.
     */
    public static function accentClass(string $severity): string
    {
        return 'accent-[#ffc53d]';
    }

    protected $fillable = [
        'screenshot_id',
        'x',
        'y',
        'area',
        'severity',
        'body',
        'number',
        'status',
        'resolution_note',
        'after_screenshot_id',
        'resolved_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'area' => 'array',
            'resolved_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Annotation $annotation): void {
            if ($annotation->status === null || $annotation->status === '') {
                // Nothing for the agent to do on a "keep" — it lands verified.
                $annotation->status = $annotation->severity === self::SEVERITY_KEEP
                    ? self::STATUS_VERIFIED
                    : self::STATUS_OPEN;
            }
        });
    }

    /**
     * Normalized region {x,y,w,h} when the human drew a rectangle.
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public function region(): ?array
    {
        $area = $this->area;

        if (! is_array($area)) {
            return null;
        }

        $w = (float) ($area['w'] ?? 0);
        $h = (float) ($area['h'] ?? 0);

        if ($w < 0.01 || $h < 0.01) {
            return null;
        }

        return [
            'x' => (float) ($area['x'] ?? $this->x),
            'y' => (float) ($area['y'] ?? $this->y),
            'w' => $w,
            'h' => $h,
        ];
    }

    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(Screenshot::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnotationComment::class)->orderBy('created_at');
    }

    /**
     * Optional "after" screenshot an agent referenced when resolving this mark.
     */
    public function afterScreenshot(): BelongsTo
    {
        return $this->belongsTo(Screenshot::class, 'after_screenshot_id');
    }
}
