<?php

namespace App\Services;

use App\DTOs\CreatedVisitorSession;
use App\Models\Widget;

final class PublicWidgetPayload
{
    /** @return array<string, mixed> */
    public function make(Widget $widget, CreatedVisitorSession $created): array
    {
        $widget->loadMissing(['bot.setting', 'bot.starterQuestions', 'bot.prechatFields']);
        $setting = $widget->bot->setting;

        return [
            'widget' => [
                'public_id' => $widget->public_id,
                'display_name' => $widget->bot->display_name,
                'welcome_message' => $widget->welcome_message,
                'accent_color' => $widget->accent_color,
                'position' => $widget->position->value,
                'online' => $widget->isLive(),
                'starter_questions' => $widget->bot->starterQuestions->pluck('question')->values()->all(),
                'prechat' => [
                    'enabled' => $setting?->prechat_enabled === true && $widget->bot->prechatFields->isNotEmpty(),
                    'fields' => $widget->bot->prechatFields->map(fn ($field): array => [
                        'key' => $field->key,
                        'label' => $field->label,
                        'type' => $field->type->value,
                        'placeholder' => $field->placeholder,
                        'required' => $field->is_required,
                        'options' => $field->options ?? [],
                    ])->values()->all(),
                ],
                'handoff_available' => $setting?->offer_human_handoff === true,
            ],
            'session' => [
                'uuid' => $created->session->uuid,
                'token' => $created->token,
                'expires_at' => $created->session->expires_at->toIso8601String(),
            ],
        ];
    }
}
