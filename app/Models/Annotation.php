<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annotation extends Model
{
    public const SEVERITY_MUST_FIX = 'must-fix';

    public const SEVERITY_NIT = 'nit';

    public const SEVERITY_QUESTION = 'question';

    public const SEVERITY_KEEP = 'keep';

    public const SEVERITY_WORDING = 'wording';

    public const SEVERITY_SPACING = 'spacing';

    public const SEVERITY_SIZE = 'size';

    public const SEVERITY_COLOR = 'color';

    public const SEVERITY_ALIGNMENT = 'alignment';

    /**
     * @return list<string>
     */
    public static function severities(): array
    {
        return array_keys(self::severityLabels());
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
            self::SEVERITY_WORDING => 'Wording',
            self::SEVERITY_SPACING => 'Spacing',
            self::SEVERITY_SIZE => 'Size',
            self::SEVERITY_COLOR => 'Color',
            self::SEVERITY_ALIGNMENT => 'Alignment',
        ];
    }

    /**
     * Tweak-type marks (scoped visual/copy changes).
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
        return self::severityLabels()[$this->severity] ?? (string) $this->severity;
    }

    /**
     * Tailwind classes for the numbered mark marker.
     */
    public function markerClass(): string
    {
        return match ($this->severity) {
            self::SEVERITY_NIT => 'bg-amber-500',
            self::SEVERITY_QUESTION => 'bg-violet-500',
            self::SEVERITY_KEEP => 'bg-emerald-500',
            self::SEVERITY_WORDING => 'bg-sky-500',
            self::SEVERITY_SPACING => 'bg-orange-500',
            self::SEVERITY_SIZE => 'bg-fuchsia-500',
            self::SEVERITY_COLOR => 'bg-pink-500',
            self::SEVERITY_ALIGNMENT => 'bg-teal-500',
            default => 'bg-rose-600',
        };
    }

    /**
     * Accent color for radio inputs.
     */
    public static function accentClass(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_NIT => 'accent-amber-500',
            self::SEVERITY_QUESTION => 'accent-violet-500',
            self::SEVERITY_KEEP => 'accent-emerald-500',
            self::SEVERITY_WORDING => 'accent-sky-500',
            self::SEVERITY_SPACING => 'accent-orange-500',
            self::SEVERITY_SIZE => 'accent-fuchsia-500',
            self::SEVERITY_COLOR => 'accent-pink-500',
            self::SEVERITY_ALIGNMENT => 'accent-teal-500',
            default => 'accent-rose-600',
        };
    }

    protected $fillable = [
        'screenshot_id',
        'x',
        'y',
        'area',
        'severity',
        'body',
        'number',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'area' => 'array',
        ];
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
}
