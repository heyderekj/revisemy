<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    public const SOURCE_CHECKLIST = 'checklist';

    public const SOURCE_OPENAI = 'openai';

    public const SOURCE_ANTHROPIC = 'anthropic';

    public const SOURCE_AGENT = 'agent';

    public const SOURCE_GUEST = 'guest';

    public const SEVERITY_SUGGESTION = 'suggestion';

    public const SEVERITY_A11Y = 'a11y';

    public const SEVERITY_POLISH = 'polish';

    public const STATUS_OPEN = 'open';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'screenshot_id',
        'source',
        'author',
        'severity',
        'body',
        'x',
        'y',
        'area',
        'related_pin',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'area' => 'array',
            'related_pin' => 'integer',
        ];
    }

    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(Screenshot::class);
    }

    public function isOpen(): bool
    {
        return ($this->status ?? self::STATUS_OPEN) === self::STATUS_OPEN;
    }

    public function isGuest(): bool
    {
        return $this->source === self::SOURCE_GUEST;
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_AGENT => 'Agent',
            self::SOURCE_OPENAI => 'Vision',
            self::SOURCE_ANTHROPIC => 'Vision',
            self::SOURCE_GUEST => $this->author ?: 'Guest',
            default => 'Checklist',
        };
    }

    /**
     * Map a suggestion severity into a human mark severity when accepting.
     * Guest suggestions already use mark severities, so those pass through.
     */
    public function pinSeverity(): string
    {
        if (in_array($this->severity, Annotation::severities(), true)) {
            return $this->severity;
        }

        return match ($this->severity) {
            self::SEVERITY_POLISH => Annotation::SEVERITY_NIT,
            self::SEVERITY_A11Y => Annotation::SEVERITY_MUST_FIX,
            default => Annotation::SEVERITY_MUST_FIX,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toAgentArray(): array
    {
        return [
            'source' => $this->source,
            'author' => $this->author,
            'severity' => $this->severity,
            'body' => $this->body,
            'area' => $this->area,
            'related_pin' => $this->related_pin,
            'status' => $this->status ?? self::STATUS_OPEN,
        ];
    }
}
