<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'trial_claimed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Limit an administrative query to subscriber accounts.
     */
    public function scopeSubscribers(Builder $query): Builder
    {
        return $query->where('role', UserRole::USER);
    }

    public static function isManagedAvatarPath(?string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', (string) $path);
        $directory = trim((string) config('admin.profile.avatar_directory'), '/');

        return $normalizedPath !== ''
            && $directory !== ''
            && ! str_contains($normalizedPath, '..')
            && Str::startsWith($normalizedPath, $directory.'/');
    }

    public function avatarUrl(): ?string
    {
        if (! self::isManagedAvatarPath($this->avatar_path)) {
            return null;
        }

        return Storage::disk(config('admin.profile.avatar_disk'))->url($this->avatar_path);
    }

    public function initials(): string
    {
        $initials = Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'A';
    }

    /** @return HasMany<SupportTicket, $this> */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'requester_id');
    }

    /** @return HasMany<SupportTicketMessage, $this> */
    public function supportTicketMessages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'sender_id');
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Bot, $this> */
    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    /** @return HasMany<Widget, $this> */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    /** @return HasMany<KnowledgeSource, $this> */
    public function knowledgeSources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<ConversationAttachment, $this> */
    public function conversationAttachments(): HasMany
    {
        return $this->hasMany(ConversationAttachment::class);
    }

    /** @return HasMany<VisitorSession, $this> */
    public function visitorSessions(): HasMany
    {
        return $this->hasMany(VisitorSession::class);
    }

    /** @return HasMany<UsageLedger, $this> */
    public function usageLedgers(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }

    /** @return HasMany<UsageCounter, $this> */
    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageCounter::class);
    }

    public function isBillingOwner(): bool
    {
        return $this->role === UserRole::USER;
    }
}
