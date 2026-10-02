<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    protected $fillable = [
        'name',
        'public_id',
        'plan',
        'billing_email',
        'credits_balance',
        'purchased_credits',
        'credits_period_start',
    ];

    /** Polar subscription statuses that still grant Plus. */
    public const PLUS_STATUSES = ['active', 'trialing', 'past_due'];

    protected function casts(): array
    {
        return [
            'credits_balance' => 'integer',
            'purchased_credits' => 'integer',
            'credits_period_start' => 'datetime',
            'polar_current_period_end' => 'datetime',
            'polar_cancel_at_period_end' => 'boolean',
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

    public function billingOrders(): HasMany
    {
        return $this->hasMany(BillingOrder::class);
    }

    /** On Plus with a Polar subscription that still grants access. */
    public function isPlusActive(): bool
    {
        return $this->normalizedPlan() === self::PLAN_PRO
            && $this->polar_subscription_id !== null
            && in_array($this->polar_subscription_status, self::PLUS_STATUSES, true);
    }

    /** Monthly grant plus purchased credits. */
    public function totalCredits(): int
    {
        return (int) $this->credits_balance + (int) $this->purchased_credits;
    }
}
