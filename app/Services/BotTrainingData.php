<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\User;

final class BotTrainingData
{
    public function __construct(private readonly CurrentSubscriptionResolver $subscriptions) {}

    public function for(User $user, Bot $bot): array
    {
        $subscription = $this->subscriptions->for($user);

        return [
            'bot' => $bot,
            'sources' => $bot->knowledgeSources()->latest('updated_at')->latest('id')->paginate(15),
            'lastTrainedAt' => $bot->knowledgeSources()->max('last_trained_at'),
            'subscriberPlanName' => $subscription?->grantsEntitlements() ? $subscription->planName() : __('No active plan'),
        ];
    }
}
