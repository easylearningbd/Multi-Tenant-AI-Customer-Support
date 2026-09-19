<?php

namespace App\Models;

use App\Enums\WidgetChannel;
use Database\Factories\VisitorSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VisitorSession extends Model
{
    /** @use HasFactory<VisitorSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'origin', 'channel', 'visitor_identifier', 'prechat_data',
        'prechat_completed_at', 'last_seen_at', 'expires_at',
    ];

    protected $hidden = ['token_hash', 'prechat_data'];

    protected function casts(): array
    {
        return [
            'channel' => WidgetChannel::class,
            'prechat_data' => 'encrypted:array',
            'prechat_completed_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function scopeForWidget(Builder $query, Widget $widget): Builder
    {
        return $query->where('user_id', $widget->user_id)
            ->where('bot_id', $widget->bot_id)
            ->where('widget_id', $widget->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now('UTC'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
