<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CurrentSubscriptionResolver;
use App\Services\PlanUsageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('usage:reconcile {--tenant= : Reconcile one subscriber owner ID}')]
#[Description('Reconcile current plan usage counters from tenant-scoped authoritative records')]
final class ReconcileUsage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CurrentSubscriptionResolver $subscriptions, PlanUsageService $usage): int
    {
        $tenant = $this->option('tenant');
        $query = User::query()->subscribers()->orderBy('id');
        if ($tenant !== null) {
            $query->whereKey((int) $tenant);
        }

        $processed = 0;
        $query->select('id')->chunkById(100, function ($owners) use ($subscriptions, $usage, &$processed): void {
            foreach ($owners as $ownerReference) {
                DB::transaction(function () use ($ownerReference, $subscriptions, $usage, &$processed): void {
                    $owner = User::query()->subscribers()->lockForUpdate()->findOrFail($ownerReference->id);
                    $subscription = $subscriptions->for($owner);
                    if (! $subscription) {
                        $this->line("Tenant {$owner->id}: no subscription; skipped.");

                        return;
                    }

                    $counters = $usage->reconcile($owner, $subscription);
                    $processed++;
                    $this->line("Tenant {$owner->id}: reconciled {$counters->count()} metrics.");
                }, 3);
            }
        });

        if ($tenant !== null && $processed === 0) {
            $this->error('The requested subscriber tenant was not found.');

            return self::FAILURE;
        }

        $this->info("Usage reconciliation complete for {$processed} tenant(s).");

        return self::SUCCESS;
    }
}
