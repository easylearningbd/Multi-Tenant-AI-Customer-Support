<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;

final class CurrentSubscriptionResolver
{
    public function for(User $billingOwner): ?Subscription
    {
        return $billingOwner->subscriptions()
            ->with('plan:id,name,slug,description,price_minor,currency,interval,trial_days,custom_pricing,is_active,sort_order,features,limits')
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }
}
