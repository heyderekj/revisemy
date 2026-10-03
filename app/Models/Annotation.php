<?php

namespace App\Models;

use App\Support\NormalizedArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public const SOURCE_HUMAN = 'human';

    public const SOURCE_GUEST = 'guest';

    public const SOURCE_CHECKLIST = 'checklist';

    public const SOURCE_VISION = 'vision';

    public const SOURCE_AGENT = 'agent';

    /**
     * @return list<string>
     */
    public static function severities(): array
    {
        return array_keys(self::severityLabels());
    }

    /**
     * Provenance for marks promoted from findings or left by the owner.
     *
     * @return list<string>
     */
    public static function sources(): array
    {
        return [
            self::SOURCE_HUMAN,
            self::SOURCE_GUEST,
            self::SOURCE_CHECKLIST,
            self::SOURCE_VISION,
            self::SOURCE_AGENT,
        ];
    }

    public static function sourceFromFinding(Finding $finding): string
    {
        return match ($finding->source) {
            Finding::SOURCE_GUEST => self::SOURCE_GUEST,
            Finding::SOURCE_CHECKLIST => self::SOURCE_CHECKLIST,
            Finding::SOURCE_OPENAI, Finding::SOURCE_ANTHROPIC => self::SOURCE_VISION,
            Finding::SOURCE_AGENT => self::SOURCE_AGENT,
            default => self::SOURCE_HUMAN,
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source ?: self::SOURCE_HUMAN) {
            self::SOURCE_GUEST => 'Guest',
            self::SOURCE_CHECKLIST => 'Checklist',
            self::SOURCE_VISION => 'Vision',
            self::SOURCE_AGENT => 'Agent',
            default => 'You',
        };
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
                'icon_bg' => 'bg-chip',
                'icon_class' => 'text-muted-foreground',
            ],
            self::STATUS_IN_PROGRESS => [
                'owner' => 'Agent',
                'droppable' => false,
                'empty' => 'Agent starts fixes here',
                'icon' => 'cpu-chip',
                'icon_bg' => 'bg-chip',
                'icon_class' => 'text-muted-foreground',
            ],
            self::STATUS_RESOLVED => [
                'owner' => 'You or agent',
                'droppable' => true,
                'empty' => 'Drop to mark resolved',
                'icon' => 'check-circle',
                'icon_bg' => 'bg-chip',
                'icon_class' => 'text-muted-foreground',
            ],
            self::STATUS_VERIFIED => [
                'owner' => 'You',
                'droppable' => true,
                'empty' => 'Drop to verify',
                'icon' => 'shield-check',
                'icon_bg' => 'bg-chip',
                'icon_class' => 'text-muted-foreground',
            ],
        ];
    }

    /**
     * Koati's tag idiom: a soft wash with a solid dot, squarer than anything
     * you can press, never bordered. Three signals say a state — attention
     * (needs you), problem, done — and sky is the agent at work. Literal
     * strings so Tailwind's scan of app/ finds every class.
     *
     * The inline MCP review reads this same map (`TONES` in review-app), so
     * the two can't disagree about what colour a status is.
     *
     * @var array<string, array{tag: string, dot: string}>
     */
    public const TONES = [
        'neutral' => ['tag' => 'bg-chip text-zinc-700', 'dot' => 'bg-zinc-400'],
        'agent' => ['tag' => 'bg-sky-50 text-sky-800', 'dot' => 'bg-sky-500'],
        'attention' => ['tag' => 'bg-attention-soft text-attention-ink', 'dot' => 'bg-attention'],
        'problem' => ['tag' => 'bg-problem-soft text-problem-ink', 'dot' => 'bg-problem'],
        'done' => ['tag' => 'bg-done-soft text-done-ink', 'dot' => 'bg-done'],
    ];

    /**
     * Which tone each status wears: resolved waits on you, verified is done.
     *
     * @return array<string, string>
     */
    public static function statusTones(): array
    {
        return [
            self::STATUS_OPEN => 'neutral',
            self::STATUS_IN_PROGRESS => 'agent',
            self::STATUS_RESOLVED => 'attention',
            self::STATUS_VERIFIED => 'done',
        ];
    }

    public function statusTone(): string
    {
        return self::statusTones()[$this->status] ?? 'neutral';
    }

    /**
     * Tailwind classes for the small status tag in the sidebar and board.
     */
    public function statusBadgeClass(): string
    {
        return self::TONES[$this->statusTone()]['tag'];
    }

    /**
     * Tailwind classes for the numbered mark marker.
     *
     * Your own marks carry the key yellow with dark ink on it in both themes —
     * white on yellow is unreadable. Guest marks are styled gray at the call
     * site so the two never compete.
     */
    public function markerClass(): string
    {
        return 'bg-accent text-accent-foreground';
    }

    /**
     * Accent color for radio inputs.
     */
    public static function accentClass(string $severity): string
    {
        return 'accent-[var(--key)]';
    }

    protected $fillable = [
        'screenshot_id',
        'x',
        'y',
        'area',
        'severity',
        'body',
        'suggested_copy',
        'question_answer',
        'source',
        'promoted_from_finding_id',
        'number',
        'status',
        'resolution_note',
        'after_screenshot_id',
        'element',
        'carried',
        'resolved_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'area' => 'array',
            'element' => 'array',
            'carried' => 'array',
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

            if ($annotation->source === null || $annotation->source === '') {
                $annotation->source = self::SOURCE_HUMAN;
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
        $normalized = NormalizedArea::from($this->area);

        if ($normalized !== null) {
            return $normalized;
        }

        // Fall back to pin x/y when area keys are incomplete but still rectangular.
        if (! is_array($this->area)) {
            return null;
        }

        return NormalizedArea::from([
            'x' => $this->area['x'] ?? $this->x,
            'y' => $this->area['y'] ?? $this->y,
            'w' => $this->area['w'] ?? $this->area['width'] ?? 0,
            'h' => $this->area['h'] ?? $this->area['height'] ?? 0,
        ]);
    }

    /**
     * What the mark is on, as a person would say it: Heading “Pricing”.
     */
    public function elementLabel(): ?string
    {
        if (! is_array($this->element) || ! is_string($this->element['kind'] ?? null)) {
            return null;
        }

        $text = trim((string) ($this->element['text'] ?? ''));

        return $text === ''
            ? $this->element['kind']
            : $this->element['kind'].' “'.Str::limit($text, 60).'”';
    }

    /**
     * The next pass's capture shows the mark's suggested copy on its element.
     * A hint for the human, never a verification.
     */
    public function looksLive(): bool
    {
        return (bool) ($this->carried['looks_live'] ?? false);
    }

    /**
     * The mark's element isn't on the next pass's capture any more.
     */
    public function missingInNextPass(): bool
    {
        return (bool) ($this->carried['missing'] ?? false);
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
