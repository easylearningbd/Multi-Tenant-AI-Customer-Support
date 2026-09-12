<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class SubscriberDashboardData
{
    /**
     * Build a tenant-safe dashboard payload.
     *
     * Workspace-owned product tables are not present yet, so this service must
     * not substitute global counts. Future data sources belong here and must be
     * explicitly scoped through the subscriber's active workspace membership.
     *
     * @return array<string, mixed>
     */
    public function for(User $subscriber): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $chartDays = collect(range(13, 1))
            ->map(fn (int $daysAgo): CarbonImmutable => $today->subDays($daysAgo))
            ->push($today);

        $workspaceLabel = Str::of($subscriber->name)
            ->squish()
            ->explode(' ')
            ->first();

        return [
            'workspaceName' => $workspaceLabel
                ? __(':name’s workspace', ['name' => $workspaceLabel])
                : __('Personal workspace'),
            'planName' => __('No active plan'),
            'conversations' => [
                'total' => 0,
                'open' => 0,
                'resolved' => 0,
            ],
            'aiResolution' => [
                'label' => __('Resolved by AI'),
                'resolved' => 0,
                'total' => 0,
                'percentage' => 0,
            ],
            'chatbots' => [
                'active' => 0,
                'used' => 0,
                'limit' => null,
                'limitLabel' => __('No active plan'),
                'percentage' => 0,
            ],
            'knowledge' => [
                'bases' => 0,
                'sources' => 0,
                'chunks' => 0,
            ],
            'chart' => [
                'labels' => $chartDays->map(fn (CarbonImmutable $date): string => $date->format('M j'))->all(),
                'values' => array_fill(0, 14, 0),
                'total' => 0,
                'thisWeek' => 0,
            ],
            'recentConversations' => [],
            'knowledgeGaps' => [
                'total' => 0,
                'lowConfidence' => 0,
                'missingSources' => 0,
                'items' => [],
            ],
            'connectedSources' => [
                'workspace' => false,
                'subscription' => false,
                'bots' => false,
                'conversations' => false,
                'knowledge' => false,
                'usage' => false,
                'gaps' => false,
            ],
        ];
    }
}
