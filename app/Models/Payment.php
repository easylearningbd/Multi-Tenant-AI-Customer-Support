<?php

namespace App\Models;

use App\Enums\PaymentAttachmentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlanInterval;
use App\Services\DecimalMoney;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'user_id',
        'plan_id',
        'subscription_id',
        'payment_method',
        'status',
        'expected_amount_minor',
        'submitted_amount_minor',
        'currency',
        'plan_name_snapshot',
        'plan_interval_snapshot',
        'plan_snapshot',
        'payer_name',
        'payer_bank_name',
        'transaction_reference',
        'transferred_at',
        'notes',
        'submitted_at',
        'paid_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'provider',
        'provider_payment_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'plan_interval_snapshot' => PlanInterval::class,
            'plan_snapshot' => 'array',
            'transferred_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'metadata' => 'array',
            'expected_amount_minor' => 'integer',
            'submitted_amount_minor' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function scopeOwnedBy(Builder $query, User $billingOwner): Builder
    {
        return $query->where('user_id', $billingOwner->id);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return HasMany<PaymentAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(PaymentAttachment::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(PaymentAttachment::class)
            ->where('type', PaymentAttachmentType::PAYMENT_PROOF);
    }

    public function formattedExpectedAmount(): string
    {
        return $this->currency.' '.DecimalMoney::fromMinor($this->expected_amount_minor);
    }

    public function formattedSubmittedAmount(): string
    {
        return $this->currency.' '.DecimalMoney::fromMinor($this->submitted_amount_minor);
    }

    public function hasAmountMismatch(): bool
    {
        return $this->expected_amount_minor !== $this->submitted_amount_minor;
    }

    public function reviewNote(): ?string
    {
        $note = $this->metadata['admin_review_note'] ?? null;

        return is_string($note) && trim($note) !== '' ? $note : null;
    }
}
