<?php

namespace App\Models;

use App\Enums\SupportTicketSenderType;
use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupportTicketMessage extends Model
{
    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'support_ticket_id',
        'sender_id',
        'sender_type',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'sender_type' => SupportTicketSenderType::class,
        ];
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return HasMany<SupportTicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }
}
