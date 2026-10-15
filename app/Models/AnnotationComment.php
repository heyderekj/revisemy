<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnotationComment extends Model
{
    public const AUTHOR_OWNER = 'owner';

    public const AUTHOR_GUEST = 'guest';

    /** A reply the agent left with reply_to_marks. It never changes the mark. */
    public const AUTHOR_AGENT = 'agent';

    protected $fillable = [
        'annotation_id',
        'author',
        'author_type',
        'from_owner',
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
            if ($comment->author_type === null || $comment->author_type === '') {
                $comment->author_type = $comment->from_owner ? self::AUTHOR_OWNER : self::AUTHOR_GUEST;
            }
        });
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }

    public function isAgent(): bool
    {
        return $this->author_type === self::AUTHOR_AGENT;
    }
}
