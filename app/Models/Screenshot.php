<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Screenshot extends Model
{
    protected $fillable = [
        'review_id',
        'path',
        'disk',
        'width',
        'height',
        'sort_order',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class)->orderBy('number');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
