<?php

namespace App\Models;

use Database\Factories\BotStarterQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BotStarterQuestion extends Model
{
    /** @use HasFactory<BotStarterQuestionFactory> */
    use HasFactory;

    protected $fillable = ['question', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** @return BelongsTo<Bot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
