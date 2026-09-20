<?php

namespace App\Jobs;

use App\Contracts\ChatCompletionProviderInterface;
use App\Contracts\ConversationQueryRewriterInterface;
use App\Contracts\KnowledgeRetrieverInterface;
use App\Contracts\RagPromptBuilderInterface;
use App\DTOs\ChatCompletionRequest;
use App\DTOs\ChatCompletionResult;
use App\DTOs\EstimatedModelCost;
use App\DTOs\QueryRewriteResult;
use App\DTOs\RagRetrievalResult;
use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use App\Enums\UsageLedgerStatus;
use App\Exceptions\ChatProviderException;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\KnowledgeChunk;
use App\Models\UsageLedger;
use App\Services\AiAnswerUsageService;
use App\Services\AiReplySanitizer;
use App\Services\ModelCostEstimator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class GenerateConversationReply implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly int $messageId,
        public readonly int $conversationId,
        public readonly int $usageLedgerId,
        public readonly int $tenantId,
        public readonly int $botId,
    ) {
        $this->onQueue((string) config('neuraldesk.queues.ai_responses'));
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->botId}:{$this->messageId}";
    }

    public function handle(
        KnowledgeRetrieverInterface $retriever,
        RagPromptBuilderInterface $prompts,
        ConversationQueryRewriterInterface $queryRewriter,
        ChatCompletionProviderInterface $provider,
        AiAnswerUsageService $usage,
        ModelCostEstimator $costs,
        AiReplySanitizer $sanitizer,
    ): void {
        $context = $this->context();
        if (! $context) {
            return;
        }
        [$bot, $conversation, $message, $ledger] = $context;
        if ($this->replyExists() || ! $this->acceptsAiReply()) {
            $usage->release($ledger);

            return;
        }

        try {
            $rewrite = $queryRewriter->rewrite($bot, $conversation, $message, $provider);
            $retrieval = $retriever->retrieve($bot, $conversation, $message, $rewrite->query);
            $prompt = $prompts->build($bot, $conversation, $message, $retrieval);
            $result = $provider->respond(new ChatCompletionRequest(
                $prompt->model,
                $prompt->instructions,
                $prompt->input,
                $prompt->temperature,
                $prompt->maxOutputTokens,
                'conversation-message-'.$message->uuid,
                hash('sha256', "tenant:{$this->tenantId}:bot:{$this->botId}:conversation:{$this->conversationId}"),
            ));
            $answer = $sanitizer->sanitize($result->text);
            if ($answer === '') {
                throw new ChatProviderException('The chat provider returned no usable answer.');
            }
            $this->persistResult($message, $ledger, $retrieval, $rewrite, $result, $answer, $usage, $costs);
        } catch (ChatProviderException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }
            report($exception);
            $this->persistFailure();
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
        $this->persistFailure();
    }

    /** @return array{Bot, Conversation, ConversationMessage, UsageLedger}|null */
    private function context(): ?array
    {
        $bot = Bot::query()->whereKey($this->botId)->where('user_id', $this->tenantId)->first();
        $conversation = Conversation::query()->whereKey($this->conversationId)->where('user_id', $this->tenantId)->where('bot_id', $this->botId)->first();
        $message = ConversationMessage::query()->whereKey($this->messageId)->forTenantBot($this->tenantId, $this->botId)->where('conversation_id', $this->conversationId)->first();
        $ledger = UsageLedger::query()->whereKey($this->usageLedgerId)->where('user_id', $this->tenantId)->where('bot_id', $this->botId)->where('conversation_id', $this->conversationId)->where('source_message_id', $this->messageId)->first();

        return $bot && $conversation && $message && $ledger ? [$bot, $conversation, $message, $ledger] : null;
    }

    private function acceptsAiReply(): bool
    {
        return DB::transaction(function (): bool {
            $conversation = Conversation::query()->whereKey($this->conversationId)->where('user_id', $this->tenantId)
                ->where('bot_id', $this->botId)->lockForUpdate()->first();

            return $conversation?->acceptsAiReplies() === true;
        }, 3);
    }

    private function replyExists(): bool
    {
        return ConversationMessage::query()->forTenantBot($this->tenantId, $this->botId)
            ->where('reply_to_message_id', $this->messageId)->exists();
    }

    private function persistResult(
        ConversationMessage $message,
        UsageLedger $ledger,
        RagRetrievalResult $retrieval,
        QueryRewriteResult $rewrite,
        ChatCompletionResult $result,
        string $answer,
        AiAnswerUsageService $usage,
        ModelCostEstimator $costs,
    ): void {
        DB::transaction(function () use ($message, $ledger, $retrieval, $rewrite, $result, $answer, $usage, $costs): void {
            $locked = $this->lockedConversation();
            if (! $locked || ! $locked->acceptsAiReplies() || $this->replyExists()) {
                $usage->release($ledger);

                return;
            }

            $inputTokens = $rewrite->inputTokens + $result->inputTokens;
            $outputTokens = $rewrite->outputTokens + $result->outputTokens;
            $cost = $this->combinedCost($costs, $rewrite, $result);
            $reply = $this->newReply($locked, $message, MessageStatus::COMPLETED, $answer);
            $reply->fill([
                'model' => $result->model,
                'provider_request_id' => $result->responseId,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'estimated_cost_minor' => $cost?->minorUnits,
                'cost_currency' => $cost?->currency,
                'latency_ms' => $result->latencyMs,
                'finish_reason' => $result->finishReason,
                'confidence' => $retrieval->confidence(),
            ]);
            $reply->save();
            $this->persistCitations($reply, $retrieval);
            $message->forceFill(['status' => MessageStatus::RECEIVED])->save();
            $locked->forceFill([
                'last_message_at' => $reply->created_at,
                'last_message_preview' => Str::limit(Str::squish($answer), 500, ''),
                'last_message_sender_type' => MessageActor::AI->value,
            ])->save();
            $usage->commit($ledger, $result->model, $inputTokens, $outputTokens, $cost?->minorUnits, $cost?->currency);
        }, 3);
    }

    private function combinedCost(
        ModelCostEstimator $costs,
        QueryRewriteResult $rewrite,
        ChatCompletionResult $result,
    ): ?EstimatedModelCost {
        $answerCost = $costs->estimate($result->model, $result->inputTokens, $result->outputTokens);
        if (! $rewrite->usedProvider) {
            return $answerCost;
        }

        $rewriteCost = $costs->estimate($rewrite->model, $rewrite->inputTokens, $rewrite->outputTokens);
        if (! $answerCost || ! $rewriteCost || $answerCost->currency !== $rewriteCost->currency) {
            return null;
        }

        return new EstimatedModelCost(
            $answerCost->minorUnits + $rewriteCost->minorUnits,
            $answerCost->currency,
        );
    }

    private function persistFailure(): void
    {
        DB::transaction(function (): void {
            $ledger = UsageLedger::query()->whereKey($this->usageLedgerId)->where('user_id', $this->tenantId)
                ->where('bot_id', $this->botId)->lockForUpdate()->first();
            if ($ledger?->status === UsageLedgerStatus::RESERVED) {
                $ledger->forceFill(['status' => UsageLedgerStatus::RELEASED, 'released_at' => now('UTC')])->save();
            }
            $conversation = $this->lockedConversation();
            $message = ConversationMessage::query()->whereKey($this->messageId)->forTenantBot($this->tenantId, $this->botId)
                ->where('conversation_id', $this->conversationId)->first();
            if (! $conversation || ! $message || $this->replyExists()) {
                return;
            }
            $message->forceFill(['status' => MessageStatus::FAILED, 'error_code' => 'ai_generation_failed'])->save();
            if ($conversation->acceptsAiReplies()) {
                $reply = $this->newReply($conversation, $message, MessageStatus::FAILED, __('The assistant could not answer safely. Please try again or contact support.'));
                $reply->error_code = 'ai_generation_failed';
                $reply->save();
            }
        }, 3);
    }

    private function lockedConversation(): ?Conversation
    {
        return Conversation::query()->whereKey($this->conversationId)->where('user_id', $this->tenantId)
            ->where('bot_id', $this->botId)->lockForUpdate()->first();
    }

    private function newReply(Conversation $conversation, ConversationMessage $message, MessageStatus $status, string $body): ConversationMessage
    {
        $reply = new ConversationMessage;
        $reply->uuid = (string) Str::uuid();
        $reply->user_id = $this->tenantId;
        $reply->bot_id = $this->botId;
        $reply->conversation_id = $conversation->id;
        $reply->reply_to_message_id = $message->id;
        $reply->actor_type = MessageActor::AI;
        $reply->status = $status;
        $reply->body = $body;

        return $reply;
    }

    private function persistCitations(ConversationMessage $reply, RagRetrievalResult $retrieval): void
    {
        foreach ($retrieval->matches as $index => $match) {
            $chunk = KnowledgeChunk::query()->forTenantBot($this->tenantId, $this->botId)
                ->whereKey($match->chunkId)->where('knowledge_source_id', $match->sourceId)->where('is_active', true)->first();
            if (! $chunk) {
                continue;
            }
            $citation = $reply->citations()->make([
                'source_uuid' => $match->sourceUuid,
                'chunk_uuid' => $match->chunkUuid,
                'source_name' => $match->sourceName,
                'rank' => $index + 1,
                'similarity_score' => $match->score,
            ]);
            $citation->user_id = $this->tenantId;
            $citation->bot_id = $this->botId;
            $citation->knowledge_source_id = $match->sourceId;
            $citation->knowledge_chunk_id = $match->chunkId;
            $citation->save();
        }
    }
}
