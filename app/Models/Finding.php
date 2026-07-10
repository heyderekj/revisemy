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

    protected $fillable = [
        'screenshot_id',
        'source',
        'severity',
        'body',
        'area',
        'related_pin',
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

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_AGENT => 'Agent',
            self::SOURCE_OPENAI => 'Vision',
            default => 'Checklist',
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
        ];
    }
}
