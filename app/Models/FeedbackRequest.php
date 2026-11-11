<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackRequest extends Model
{
    protected $fillable = [
        'annotation_id', 'group_id', 'token', 'reviewer_name', 'email', 'question', 'expires_at',
        'revoked_at', 'emailed_at', 'respondent', 'response', 'responded_at',
    ];

    protected $hidden = ['token', 'email'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'emailed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public static function isPersonalToken(string $token): bool
    {
        return preg_match('/^f-[A-Za-z0-9]{40}$/D', $token) === 1;
    }

    public static function forToken(string $token): ?self
    {
        if (! self::isPersonalToken($token)) {
            return null;
        }

        $request = self::query()->where('token', $token)->first();

        return $request && hash_equals($request->token, $token) ? $request : null;
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }

    public function isOpen(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public static function accessible(string $token): self
    {
        $feedback = self::forToken($token);
        abort_unless($feedback && $feedback->isOpen() && ! $feedback->review->isExpired(), 404);

        return $feedback;
    }

    public function reviewerName(): string
    {
        return $this->reviewer_name ?? $this->respondent ?? 'Guest reviewer';
    }

    public function url(): string
    {
        return route('feedback.show', $this->token);
    }
}
