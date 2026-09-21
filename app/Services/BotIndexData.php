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
        $used = $subscriber->bots()->where('is_active', true)->count();
        $limit = $hasEntitlements ? $subscription->limitFor('chatbots_limit') : null;
        $knowledgeBaseLimit = $hasEntitlements ? $subscription->limitFor('knowledge_bases_limit') : null;
        $unlimited = $limit === 0 && $knowledgeBaseLimit === 0;
        $effectiveLimit = collect([$limit, $knowledgeBaseLimit])
            ->filter(fn (?int $value): bool => $value !== null && $value > 0)
            ->min();

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
                'limit' => $effectiveLimit,
                'unlimited' => $unlimited,
                'canCreate' => $hasEntitlements && ($unlimited || $used < $effectiveLimit),
                'label' => $effectiveLimit === null && ! $unlimited
                    ? __('No active plan')
                    : ($unlimited
                        ? __(':used used · Unlimited', ['used' => number_format($used)])
                        : __(':used of :limit used', [
                            'used' => number_format($used),
                            'limit' => number_format($effectiveLimit),
                        ])),
            ],
        ];
    }
}
