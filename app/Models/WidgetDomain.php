<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WidgetDomain extends Model
{
    protected $fillable = ['origin'];

    public function scopeForWidget(Builder $query, Widget $widget): Builder
    {
        return $query->where('user_id', $widget->user_id)
            ->where('bot_id', $widget->bot_id)
            ->where('widget_id', $widget->id);
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
}
