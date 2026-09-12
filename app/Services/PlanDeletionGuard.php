<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PlanDeletionGuard
{
    /** @var list<string> */
    private const REFERENCE_TABLES = [
        'subscriptions',
        'payments',
        'invoices',
        'checkout_sessions',
    ];

    public function isReferenced(Plan $plan): bool
    {
        foreach (self::REFERENCE_TABLES as $table) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'plan_id')
                && DB::table($table)->where('plan_id', $plan->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
