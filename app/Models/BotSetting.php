<?php

namespace App\Models;

use App\Enums\BotTone;
use Database\Factories\BotSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BotSetting extends Model
{
    /** @use HasFactory<BotSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'welcome_message',
        'prechat_enabled',
        'tone',
        'primary_language',
        'persona',
        'fallback_message',
        'offer_human_handoff',
        'answer_only_from_knowledge_base',
        'model_override',
        'temperature',
        'max_output_tokens',
        'kb_confidence',
    ];

    protected function casts(): array
    {
        return [
            'prechat_enabled' => 'boolean',
            'tone' => BotTone::class,
            'offer_human_handoff' => 'boolean',
            'answer_only_from_knowledge_base' => 'boolean',
            'temperature' => 'decimal:2',
            'max_output_tokens' => 'integer',
            'kb_confidence' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Bot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
