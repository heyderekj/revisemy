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
        'author_kind',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'from_owner' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AnnotationComment $comment): void {
            if ($comment->author_kind === null || $comment->author_kind === '') {
                $comment->author_kind = $comment->from_owner ? AnnotationEvent::ACTOR_OWNER : AnnotationEvent::ACTOR_GUEST;
            }
        });
    }

    public function isFromAgent(): bool
    {
        return $this->author_kind === AnnotationEvent::ACTOR_AGENT;
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }
}
