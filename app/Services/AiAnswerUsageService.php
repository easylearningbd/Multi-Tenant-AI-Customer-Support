<?php

namespace App\Services;

use App\Enums\PlanMetric;
use App\Enums\UsageLedgerStatus;
use App\Enums\UsageType;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\UsageLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiAnswerUsageService
{
    public function __construct(
        private readonly PlanUsageService $usage,
    ) {}

    public function reserve(User $user, Bot $bot, Conversation $conversation, ConversationMessage $message): UsageLedger
    {
        abort_unless($bot->user_id === $user->id && $conversation->user_id === $user->id && $conversation->bot_id === $bot->id, 404);
        abort_unless($message->user_id === $user->id && $message->bot_id === $bot->id && $message->conversation_id === $conversation->id, 404);

        $eventKey = hash('sha256', "ai-answer:{$user->id}:{$bot->id}:{$message->uuid}");

        return DB::transaction(function () use ($user, $bot, $conversation, $message, $eventKey): UsageLedger {
            $owner = User::query()->subscribers()->lockForUpdate()->findOrFail($user->id);
            $existing = UsageLedger::query()->where('event_key', $eventKey)->first();
            if ($existing) {
                abort_unless($existing->user_id === $owner->id && $existing->bot_id === $bot->id, 404);

                return $existing;
            }

            $subscription = $this->usage->activeSubscription($owner);
            $period = $this->usage->period($subscription);
            $this->usage->reserve($owner, $subscription, PlanMetric::AI_ANSWERS, 1);

            $ledger = new UsageLedger;
            $ledger->uuid = (string) Str::uuid();
            $ledger->user_id = $owner->id;
            $ledger->bot_id = $bot->id;
            $ledger->subscription_id = $subscription->id;
            $ledger->plan_id = $subscription->plan_id;
            $ledger->conversation_id = $conversation->id;
            $ledger->source_message_id = $message->id;
            $ledger->fill([
                'event_key' => $eventKey,
                'type' => UsageType::AI_ANSWER,
                'status' => UsageLedgerStatus::RESERVED,
                'quantity' => 1,
                'period_starts_at' => $period->startsAt,
                'period_ends_at' => $period->endsAt,
            ]);
            $ledger->save();

            return $ledger;
        }, 3);
    }

    public function commit(UsageLedger $ledger, string $model, int $inputTokens, int $outputTokens, ?int $estimatedCostMinor = null, ?string $costCurrency = null): void
    {
        DB::transaction(function () use ($ledger, $model, $inputTokens, $outputTokens, $estimatedCostMinor, $costCurrency): void {
            $locked = UsageLedger::query()->whereKey($ledger->id)->where('user_id', $ledger->user_id)
                ->where('bot_id', $ledger->bot_id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== UsageLedgerStatus::RESERVED) {
                return;
            }
            $locked->forceFill([
                'status' => UsageLedgerStatus::COMMITTED,
                'model' => $model,
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'estimated_cost_minor' => $estimatedCostMinor,
                'cost_currency' => $costCurrency,
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

    public function reservationIsValid(UsageLedger $ledger): bool
    {
        $fresh = UsageLedger::query()->with('subscription')->whereKey($ledger->id)
            ->where('user_id', $ledger->user_id)->where('bot_id', $ledger->bot_id)->first();

        return $fresh?->status === UsageLedgerStatus::RESERVED
            && $fresh->subscription?->grantsEntitlements() === true;
    }
}
