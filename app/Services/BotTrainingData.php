<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\User;

final class BotTrainingData
{
    public function __construct(
        private readonly CurrentSubscriptionResolver $subscriptions,
        private readonly PlanUsageService $usage,
    ) {}

    public function for(User $user, Bot $bot): array
    {
        $subscription = $this->subscriptions->for($user);
        $usage = $subscription ? $this->usage->summary($user, $subscription)->keyBy('key') : collect();
        $sourceCapacity = $usage->get('knowledge_sources_limit');
        $storageCapacity = $usage->get('storage_mb_limit');
        $hasEntitlements = $subscription?->grantsEntitlements() === true;

        return [
            'bot' => $bot,
            'sources' => $bot->knowledgeSources()->latest('updated_at')->latest('id')->paginate(15),
            'lastTrainedAt' => $bot->knowledgeSources()->max('last_trained_at'),
            'subscriberPlanName' => $subscription?->grantsEntitlements() ? $subscription->planName() : __('No active plan'),
            'sourceCapacity' => $sourceCapacity,
            'storageCapacity' => $storageCapacity,
            'canAddSource' => $hasEntitlements && ((bool) ($sourceCapacity['unlimited'] ?? false) || ($sourceCapacity['remaining'] ?? 0) > 0),
            'canUploadFile' => $hasEntitlements
                && ((bool) ($sourceCapacity['unlimited'] ?? false) || ($sourceCapacity['remaining'] ?? 0) > 0)
                && ((bool) ($storageCapacity['unlimited'] ?? false) || ($storageCapacity['remaining'] ?? 0) > 0),
        ];
    }
}
