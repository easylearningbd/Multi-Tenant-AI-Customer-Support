<?php

namespace App\Enums;

enum PlanMetric: string
{
    case AI_ANSWERS = 'ai_answers_per_month';
    case CHATBOTS = 'chatbots_limit';
    case KNOWLEDGE_BASES = 'knowledge_bases_limit';
    case KNOWLEDGE_SOURCES = 'knowledge_sources_limit';
    case TEAM_MEMBERS = 'team_members_limit';
    case STORAGE = 'storage_mb_limit';

    public function label(): string
    {
        return match ($this) {
            self::AI_ANSWERS => __('AI answers / month'),
            self::CHATBOTS => __('Chatbots'),
            self::KNOWLEDGE_BASES => __('Knowledge bases'),
            self::KNOWLEDGE_SOURCES => __('Knowledge sources'),
            self::TEAM_MEMBERS => __('Team members'),
            self::STORAGE => __('Storage MB'),
        };
    }

    public function storesBytes(): bool
    {
        return $this === self::STORAGE;
    }
}
