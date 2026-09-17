<?php

namespace App\Services;

use App\Enums\UsageLedgerStatus;
use App\Enums\UsageType;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\UsageLedger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AiAnswerUsageService
{
    public function __construct(
        private readonly CurrentSubscriptionResolver $subscriptions,
        private readonly PlanLimitService $limits,
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

            $subscription = $this->subscriptions->for($owner);
            if (! $subscription || ! $subscription->grantsEntitlements()) {
                throw ValidationException::withMessages([
                    'plan_limit' => __('An active subscription is required before using AI answers.'),
                ]);
            }

            [$periodStart, $periodEnd] = $this->billingPeriod($subscription);
            $usage = (int) UsageLedger::query()
                ->forPeriod($owner->id, UsageType::AI_ANSWER, $periodStart, $periodEnd)
                ->whereIn('status', [UsageLedgerStatus::RESERVED, UsageLedgerStatus::COMMITTED])
                ->sum('quantity');
            $this->limits->ensureAllows($subscription, 'ai_answers_per_month', $usage);

            $ledger = new UsageLedger;
            $ledger->uuid = (string) Str::uuid();
            $ledger->user_id = $owner->id;
            $ledger->bot_id = $bot->id;
            $ledger->subscription_id = $subscription->id;
            $ledger->conversation_id = $conversation->id;
            $ledger->source_message_id = $message->id;
            $ledger->fill([
                'event_key' => $eventKey,
                'type' => UsageType::AI_ANSWER,
                'status' => UsageLedgerStatus::RESERVED,
                'quantity' => 1,
                'period_starts_at' => $periodStart,
                'period_ends_at' => $periodEnd,
            ]);
            $ledger->save();

            return $ledger;
        }, 3);
    }

    public function commit(UsageLedger $ledger, string $model, int $inputTokens, int $outputTokens, ?int $estimatedCostMinor = null, ?string $costCurrency = null): void
    {
        UsageLedger::query()->whereKey($ledger->id)->where('user_id', $ledger->user_id)
            ->where('bot_id', $ledger->bot_id)->where('status', UsageLedgerStatus::RESERVED)
            ->update([
                'status' => UsageLedgerStatus::COMMITTED,
                'model' => $model,
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'estimated_cost_minor' => $estimatedCostMinor,
                'cost_currency' => $costCurrency,
                'committed_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
    }

    public function release(UsageLedger $ledger): void
    {
        UsageLedger::query()->whereKey($ledger->id)->where('user_id', $ledger->user_id)
            ->where('bot_id', $ledger->bot_id)->where('status', UsageLedgerStatus::RESERVED)
            ->update([
                'status' => UsageLedgerStatus::RELEASED,
                'released_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function billingPeriod($subscription): array
    {
        $now = CarbonImmutable::now('UTC');
        $start = $subscription->current_period_starts_at
            ?? $subscription->starts_at
            ?? $now->startOfMonth();
        $end = $subscription->current_period_ends_at
            ?? $subscription->trial_ends_at
            ?? $now->endOfMonth();

        return [CarbonImmutable::instance($start)->utc(), CarbonImmutable::instance($end)->utc()];
    }
}
