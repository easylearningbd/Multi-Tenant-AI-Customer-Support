<?php

namespace App\Models;

use App\Enums\WidgetPosition;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;

    protected $fillable = ['is_enabled', 'accent_color', 'position', 'welcome_message'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'position' => WidgetPosition::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeOwnedBy(Builder $query, User $owner): Builder
    {
        return $query->where('user_id', $owner->id);
    }

    public function scopeForBot(Builder $query, Bot $bot): Builder
    {
        return $query->where('user_id', $bot->user_id)->where('bot_id', $bot->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(WidgetDomain::class)->orderBy('origin');
    }

    public function visitorSessions(): HasMany
    {
        return $this->hasMany(VisitorSession::class);
    }

    public function isLive(): bool
    {
        return $this->is_enabled && $this->bot?->is_active === true;
    }
}
