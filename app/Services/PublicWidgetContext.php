<?php

namespace App\Services;

use App\Models\Widget;

final class PublicWidgetContext
{
    public function __construct(private readonly CurrentSubscriptionResolver $subscriptions) {}

    public function resolve(string $publicId): Widget
    {
        $widget = Widget::query()
            ->where('public_id', $publicId)
            ->where('is_enabled', true)
            ->with([
                'user:id,role',
                'bot' => fn ($query) => $query->where('is_active', true)
                    ->with(['setting', 'starterQuestions', 'prechatFields']),
            ])
            ->firstOrFail();

        abort_unless($widget->bot !== null && $this->subscriptions->for($widget->user)?->grantsEntitlements() === true, 404);

        return $widget;
    }
}
