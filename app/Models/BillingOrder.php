<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One paid Polar order. The unique polar_order_id makes webhook grants
 * idempotent: a redelivered order.paid finds its row and grants nothing.
 */
class BillingOrder extends Model
{
    public const KIND_PLUS = 'plus';

    public const KIND_PACK = 'pack';

    protected $fillable = [
        'polar_order_id',
        'workspace_id',
        'kind',
        'product_key',
        'billing_reason',
        'credits_granted',
        'amount_cents',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'credits_granted' => 'integer',
            'amount_cents' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
