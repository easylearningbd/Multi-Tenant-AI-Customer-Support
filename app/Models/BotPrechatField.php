<?php

namespace App\Models;

use App\Enums\PrechatFieldType;
use Database\Factories\BotPrechatFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BotPrechatField extends Model
{
    /** @use HasFactory<BotPrechatFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'type',
        'placeholder',
        'is_required',
        'position',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'type' => PrechatFieldType::class,
            'is_required' => 'boolean',
            'position' => 'integer',
            'options' => 'array',
        ];
    }

    /** @return BelongsTo<Bot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
