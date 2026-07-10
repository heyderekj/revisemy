<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Screenshot extends Model
{
    public const OPINION_IDLE = 'idle';

    public const OPINION_QUEUED = 'queued';

    public const OPINION_READY = 'ready';

    public const OPINION_FAILED = 'failed';

    protected $fillable = [
        'review_id',
        'path',
        'disk',
        'width',
        'height',
        'sort_order',
        'second_opinion_status',
        'second_opinion_error',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class)->orderBy('number');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function secondOpinionIsPending(): bool
    {
        return $this->second_opinion_status === self::OPINION_QUEUED;
    }
}
