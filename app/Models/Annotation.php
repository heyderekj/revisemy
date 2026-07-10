<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annotation extends Model
{
    public const SEVERITY_MUST_FIX = 'must-fix';

    public const SEVERITY_NIT = 'nit';

    protected $fillable = [
        'screenshot_id',
        'x',
        'y',
        'severity',
        'body',
        'number',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
        ];
    }

    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(Screenshot::class);
    }
}
