<?php

namespace App\Services\Admin;

use App\Models\User;

final class AdminDashboardData
{
    /**
     * Build the dashboard presentation data from modules that currently exist.
     *
     * Payment, refund, support-ticket, and login-activity sources will replace
     * the empty collections when those modules are implemented.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $recentUsers = User::query()
            ->latest()
            ->limit(5)
            ->get(['name', 'role', 'created_at'])
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'description' => ucfirst($user->role->value).' account created',
                'time' => $user->created_at?->diffForHumans() ?? __('Recently'),
            ])
            ->all();

        return [
            'dataNotice' => __('Payment and support modules are not connected yet. Their cards show safe empty states.'),
            'summary' => [
                [
                    'label' => __('Net Revenue'),
                    'value' => '—',
                    'status' => __('Awaiting data'),
                    'tone' => 'primary',
                    'icon' => 'iconoir-stat-up',
                ],
                [
                    'label' => __('Total Payments'),
                    'value' => '—',
                    'status' => __('Awaiting data'),
                    'tone' => 'success',
                    'icon' => 'iconoir-check-circle',
                ],
                [
                    'label' => __('Pending Payments'),
                    'value' => '—',
                    'status' => __('Awaiting data'),
                    'tone' => 'warning',
                    'icon' => 'iconoir-clock',
                ],
                [
                    'label' => __('Payment Issues'),
                    'value' => '—',
                    'status' => __('Awaiting data'),
                    'tone' => 'danger',
                    'icon' => 'iconoir-warning-circle',
                ],
            ],
            'revenue' => [
                'hasData' => false,
                'labels' => [],
                'series' => [
                    ['name' => __('Net Revenue'), 'data' => []],
                    ['name' => __('Collected'), 'data' => []],
                    ['name' => __('Refunds'), 'data' => []],
                ],
            ],
            'paymentHealth' => [
                'hasData' => false,
                'paidPercentage' => null,
                'completed' => null,
                'failed' => null,
                'pendingRate' => null,
                'failureRate' => null,
                'netRevenue' => null,
                'refunded' => null,
                'gateways' => [],
            ],
            'activity' => [
                'payments' => [],
                'users' => $recentUsers,
                'logins' => [],
            ],
            'tickets' => [
                'open' => null,
                'pending' => null,
                'urgent' => null,
                'recent' => [],
            ],
        ];
    }
}
