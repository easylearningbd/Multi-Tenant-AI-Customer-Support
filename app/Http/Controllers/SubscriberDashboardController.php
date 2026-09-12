<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriberDashboardData;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SubscriberDashboardController extends Controller
{
    public function __invoke(Request $request, SubscriberDashboardData $dashboardData): View
    {
        /** @var User $subscriber */
        $subscriber = $request->user();

        return view('dashboard', [
            'dashboard' => $dashboardData->for($subscriber),
        ]);
    }
}
