<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Paddle\Billable;

class Workspace extends Model
{
    use Billable;

    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    protected $fillable = [
        'name',
        'public_id',
        'plan',
        'billing_email',
        'credits_balance',
        'credits_period_start',
    ];

    protected function casts(): array
    {
        return [
            'credits_balance' => 'integer',
            'credits_period_start' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace): void {
            $workspace->public_id ??= (string) Str::ulid();
            $workspace->plan ??= self::PLAN_FREE;
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function normalizedPlan(): string
    {
        return $this->plan === self::PLAN_PRO ? self::PLAN_PRO : self::PLAN_FREE;
    }

    public function reviewRetentionDays(): int
    {
        $plan = $this->normalizedPlan();

        return (int) config(
            "billing.plans.{$plan}.review_retention_days",
            config('billing.plans.free.review_retention_days', 7),
        );
    }

    public function paddleName(): ?string
    {
        return $this->name ?: 'ReviseMy workspace';
    }

    public function paddleEmail(): ?string
    {
        return $this->billing_email;
    }
}
