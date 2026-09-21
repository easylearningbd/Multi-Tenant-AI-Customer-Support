<?php

namespace App\Services;

use App\Enums\PlanMetric;
use App\Enums\UsageLedgerStatus;
use App\Enums\UsageType;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Models\UsageLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class KnowledgeStorageUsageService
{
    public function __construct(private readonly PlanUsageService $usage) {}

    public function reserve(User $owner, Bot $bot, int $bytes, int $sourceCount): ?UsageLedger
    {
        if ($bytes < 1) {
            return null;
        }

        return DB::transaction(function () use ($owner, $bot, $bytes, $sourceCount): UsageLedger {
            $lockedOwner = User::query()->subscribers()->lockForUpdate()->findOrFail($owner->id);
            abort_unless($bot->user_id === $lockedOwner->id, 404);
            $subscription = $this->usage->activeSubscription($lockedOwner);
            $this->usage->ensureWithinLimit(
                $subscription,
                PlanMetric::KNOWLEDGE_SOURCES,
                $lockedOwner->knowledgeSources()->count(),
                $sourceCount,
            );
            $period = $this->usage->period($subscription);
            $this->usage->reserve($lockedOwner, $subscription, PlanMetric::STORAGE, $bytes);

            $ledger = new UsageLedger;
            $ledger->uuid = (string) Str::uuid();
            $ledger->user_id = $lockedOwner->id;
            $ledger->bot_id = $bot->id;
            $ledger->subscription_id = $subscription->id;
            $ledger->plan_id = $subscription->plan_id;
            $ledger->fill([
                'event_key' => hash('sha256', 'knowledge-storage:'.Str::uuid()),
                'type' => UsageType::KNOWLEDGE_STORAGE,
                'status' => UsageLedgerStatus::RESERVED,
                'quantity' => $bytes,
                'period_starts_at' => $period->startsAt,
                'period_ends_at' => $period->endsAt,
            ]);
            $ledger->save();

            return $ledger;
        }, 3);
    }

    public function commit(UsageLedger $ledger, ?KnowledgeSource $source = null): void
    {
        DB::transaction(function () use ($ledger, $source): void {
            $locked = UsageLedger::query()->whereKey($ledger->id)->where('user_id', $ledger->user_id)
                ->where('bot_id', $ledger->bot_id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== UsageLedgerStatus::RESERVED) {
                return;
            }
            $locked->forceFill([
                'status' => UsageLedgerStatus::COMMITTED,
                'knowledge_source_id' => $source?->id,
                'committed_at' => now('UTC'),
            ])->save();
            $this->usage->consumeLedgerReservation($locked);
        }, 3);
    }

    public function release(UsageLedger $ledger): void
    {
        DB::transaction(function () use ($ledger): void {
            $locked = UsageLedger::query()->whereKey($ledger->id)->where('user_id', $ledger->user_id)
                ->where('bot_id', $ledger->bot_id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== UsageLedgerStatus::RESERVED) {
                return;
            }
            $locked->forceFill([
                'status' => UsageLedgerStatus::RELEASED,
                'released_at' => now('UTC'),
            ])->save();
            $this->usage->releaseLedgerReservation($locked);
        }, 3);
    }
}
