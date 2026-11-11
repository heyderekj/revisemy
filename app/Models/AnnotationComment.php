<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AnnotationComment extends Model
{
    public const AUTHOR_OWNER = 'owner';

    public const AUTHOR_GUEST = 'guest';

    /** A reply the agent left with reply_to_marks. It never changes the mark. */
    public const AUTHOR_AGENT = 'agent';

    public const REACTIONS = [
        'like' => ['👍', 'Like'],
        'love' => ['❤️', 'Love'],
        'celebrate' => ['🎉', 'Celebrate'],
        'eyes' => ['👀', 'Looking into it'],
    ];

    protected $hidden = ['reactions'];

    protected $fillable = [
        'element_references',
        'annotation_id',
        'author',
        'author_type',
        'from_owner',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'element_references' => 'array',
            'from_owner' => 'boolean',
            'reactions' => 'array',
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

    public static function reactionActor(bool $owner): string
    {
        return $owner ? 'owner' : hash_hmac('sha256', session()->getId(), config('app.key'));
    }

    /** @return array<string, array{count: int, selected: bool}> */
    public function reactionSummary(string $actor): array
    {
        $summary = [];

        foreach ($this->reactions ?? [] as $reaction => $actors) {
            $summary[$reaction] = [
                'count' => count($actors),
                'selected' => isset($actors[$actor]),
            ];
        }

        return $summary;
    }

    public function setReaction(string $reaction, string $actor, bool $selected): void
    {
        if (! isset(self::REACTIONS[$reaction])) {
            return;
        }

        DB::transaction(function () use ($reaction, $actor, $selected): void {
            $comment = static::query()->lockForUpdate()->find($this->id);

            if (! $comment) {
                return;
            }

            $reactions = $comment->reactions ?? [];

            if ($selected) {
                $reactions[$reaction][$actor] = true;
            } else {
                unset($reactions[$reaction][$actor]);

                if (empty($reactions[$reaction])) {
                    unset($reactions[$reaction]);
                }
            }

            $comment->reactions = $reactions ?: null;
            $comment->save();
        }, 3);
    }

    public function isAgent(): bool
    {
        return $this->author_type === self::AUTHOR_AGENT;
    }
}
