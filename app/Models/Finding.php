<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    public const SOURCE_CHECKLIST = 'checklist';

    public const SOURCE_OPENAI = 'openai';

    public const SOURCE_AGENT = 'agent';

    public const SEVERITY_SUGGESTION = 'suggestion';

    public const SEVERITY_A11Y = 'a11y';

    public const SEVERITY_POLISH = 'polish';

    public const STATUS_OPEN = 'open';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'screenshot_id',
        'source',
        'severity',
        'body',
        'area',
        'related_pin',
        'status',
    ];

    protected function casts(): array
    {
        return [
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

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_AGENT => 'Agent',
            self::SOURCE_OPENAI => 'Vision',
            default => 'Checklist',
        };
    }

    /**
     * Map a second-opinion severity into a human mark severity when accepting.
     */
    public function pinSeverity(): string
    {
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
            'severity' => $this->severity,
            'body' => $this->body,
            'area' => $this->area,
            'related_pin' => $this->related_pin,
            'status' => $this->status ?? self::STATUS_OPEN,
        ];
    }
}
