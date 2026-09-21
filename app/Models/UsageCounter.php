<?php

namespace App\Models;

use App\Enums\PlanMetric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UsageCounter extends Model
{
    protected $fillable = [
        'user_id', 'subscription_id', 'plan_id', 'metric',
        'period_starts_at', 'period_ends_at', 'used', 'reserved',
    ];

    protected function casts(): array
    {
        return [
            'metric' => PlanMetric::class,
            'period_starts_at' => 'immutable_datetime',
            'period_ends_at' => 'immutable_datetime',
            'used' => 'integer',
            'reserved' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
