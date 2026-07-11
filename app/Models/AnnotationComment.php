<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnotationComment extends Model
{
    protected $fillable = [
        'annotation_id',
        'author',
        'from_owner',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'from_owner' => 'boolean',
        ];
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }
}
