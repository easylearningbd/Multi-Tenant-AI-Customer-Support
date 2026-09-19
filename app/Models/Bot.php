<?php

namespace App\Models;

use Database\Factories\BotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Bot extends Model
{
    /** @use HasFactory<BotFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeOwnedBy(Builder $query, User $owner): Builder
    {
        return $query->whereBelongsTo($owner);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<BotSetting, $this> */
    public function setting(): HasOne
    {
        return $this->hasOne(BotSetting::class);
    }

    /** @return HasOne<Widget, $this> */
    public function widget(): HasOne
    {
        return $this->hasOne(Widget::class);
    }

    /** @return HasMany<BotStarterQuestion, $this> */
    public function starterQuestions(): HasMany
    {
        return $this->hasMany(BotStarterQuestion::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return HasMany<BotPrechatField, $this> */
    public function prechatFields(): HasMany
    {
        return $this->hasMany(BotPrechatField::class)
            ->orderBy('position')
            ->orderBy('id');
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

    /** @return HasMany<VisitorSession, $this> */
    public function visitorSessions(): HasMany
    {
        return $this->hasMany(VisitorSession::class);
    }
}
