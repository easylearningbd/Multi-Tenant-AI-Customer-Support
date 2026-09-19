<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BotIndexData
{
    public function __construct(private readonly CurrentSubscriptionResolver $subscriptions) {}

    /** @return array<string, mixed> */
    public function for(User $subscriber): array
    {
        $subscription = $this->subscriptions->for($subscriber);
        $hasEntitlements = $subscription?->grantsEntitlements() === true;
        $used = $subscriber->bots()->count();
        $limit = $hasEntitlements ? $subscription->limitFor('chatbots_limit') : null;
        $unlimited = $limit === 0;

        /** @var LengthAwarePaginator $bots */
        $bots = $subscriber->bots()
            ->withCount(['knowledgeSources', 'widget'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(12);

        return [
            'bots' => $bots,
            'subscriberPlanName' => $hasEntitlements ? $subscription->planName() : __('No active plan'),
            'capacity' => [
                'used' => $used,
                'limit' => $limit,
                'unlimited' => $unlimited,
                'canCreate' => $hasEntitlements && ($unlimited || $used < $limit),
                'label' => $limit === null
                    ? __('No active plan')
                    : ($unlimited
                        ? __(':used used · Unlimited', ['used' => number_format($used)])
                        : __(':used of :limit used', [
                            'used' => number_format($used),
                            'limit' => number_format($limit),
                        ])),
            ],
        ];
    }
}
