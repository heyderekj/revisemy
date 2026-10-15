<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a mark's life — started, resolved, reopened, verified — and who
 * took it. Written by MarkLifecycleService::transition(); read, with the
 * mark's comments, by App\Support\MarkTimeline.
 */
class AnnotationEvent extends Model
{
    public const ACTOR_OWNER = 'owner';

    public const ACTOR_GUEST = 'guest';

    public const ACTOR_AGENT = 'agent';

    public const TYPE_STARTED = 'started';

    public const TYPE_RESOLVED = 'resolved';

    public const TYPE_REOPENED = 'reopened';

    public const TYPE_VERIFIED = 'verified';

    protected $fillable = [
        'annotation_id',
        'actor',
        'type',
        'body',
        'after_screenshot_id',
    ];

    /**
     * The event a move to this status records.
     */
    public static function typeForStatus(string $status): ?string
    {
        return match ($status) {
            Annotation::STATUS_IN_PROGRESS => self::TYPE_STARTED,
            Annotation::STATUS_RESOLVED => self::TYPE_RESOLVED,
            Annotation::STATUS_OPEN => self::TYPE_REOPENED,
            Annotation::STATUS_VERIFIED => self::TYPE_VERIFIED,
            default => null,
        };
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }

    public function afterScreenshot(): BelongsTo
    {
        return $this->belongsTo(Screenshot::class, 'after_screenshot_id');
    }
}
