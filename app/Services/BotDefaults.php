<?php

namespace App\Services;

use App\Enums\BotTone;
use Illuminate\Support\Str;

final class BotDefaults
{
    public function displayName(string $name, ?string $displayName = null): string
    {
        $displayName = Str::squish((string) $displayName);

        return $displayName !== '' ? $displayName : Str::squish($name);
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            'welcome_message' => (string) config('neuraldesk.bots.defaults.welcome_message'),
            'prechat_enabled' => (bool) config('neuraldesk.bots.defaults.prechat_enabled'),
            'tone' => BotTone::from((string) config('neuraldesk.bots.defaults.tone'))->value,
            'primary_language' => (string) config('neuraldesk.bots.defaults.primary_language'),
            'persona' => (string) config('neuraldesk.bots.defaults.persona'),
            'fallback_message' => (string) config('neuraldesk.bots.defaults.fallback_message'),
            'offer_human_handoff' => (bool) config('neuraldesk.bots.defaults.offer_human_handoff'),
            'answer_only_from_knowledge_base' => (bool) config('neuraldesk.bots.defaults.answer_only_from_knowledge_base'),
            'model_override' => null,
            'temperature' => (string) config('neuraldesk.ai.defaults.temperature'),
            'max_output_tokens' => (int) config('neuraldesk.ai.defaults.max_output_tokens'),
            'kb_confidence' => (string) config('neuraldesk.ai.defaults.kb_confidence'),
        ];
    }
}
