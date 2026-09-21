<?php

namespace App\Enums;

enum UsageType: string
{
    case AI_ANSWER = 'ai_answer';
    case KNOWLEDGE_STORAGE = 'knowledge_storage';

    public function metric(): PlanMetric
    {
        return match ($this) {
            self::AI_ANSWER => PlanMetric::AI_ANSWERS,
            self::KNOWLEDGE_STORAGE => PlanMetric::STORAGE,
        };
    }
}
