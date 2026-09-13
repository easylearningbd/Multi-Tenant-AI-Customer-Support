<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriberBillingData;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BillingController extends Controller
{
    public function __invoke(Request $request, SubscriberBillingData $billingData): View
    {
        /** @var User $billingOwner */
        $billingOwner = $request->user();

        $billing = $billingData->for($billingOwner);

        return view('billing.index', [
            'billing' => $billing,
            'subscriberPlanName' => $billing['current']['name'] ?? __('No active plan'),
        ]);
    }
}
