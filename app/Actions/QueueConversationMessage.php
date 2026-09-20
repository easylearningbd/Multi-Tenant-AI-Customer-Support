<?php

namespace App\Actions;

use App\DTOs\ConversationOrigin;
use App\DTOs\QueuedConversationMessage;
use App\Enums\ConversationHandlingMode;
use App\Enums\ConversationMessageType;
use App\Enums\ConversationStatus;
use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use App\Jobs\GenerateConversationReply;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\AiAnswerUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class QueueConversationMessage
{
    public function __construct(private readonly AiAnswerUsageService $usage) {}

    public function execute(User $user, Bot $bot, string $body, string $idempotencyKey, ?string $conversationUuid = null, ?ConversationOrigin $origin = null): QueuedConversationMessage
    {
        abort_unless($bot->user_id === $user->id, 404);
        if (! $bot->is_active) {
            throw ValidationException::withMessages(['bot' => __('This bot is inactive and cannot answer new messages.')]);
        }

        return DB::transaction(function () use ($user, $bot, $body, $idempotencyKey, $conversationUuid, $origin): QueuedConversationMessage {
            User::query()->subscribers()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedBot = Bot::query()->ownedBy($user)->whereKey($bot->id)->lockForUpdate()->firstOrFail();

            $existing = ConversationMessage::query()->forTenantBot($user->id, $lockedBot->id)
                ->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $existingConversation = $existing->conversation()->firstOrFail();
                abort_unless($origin?->visitorSessionId === null || $existingConversation->visitor_session_id === $origin->visitorSessionId, 404);

                return new QueuedConversationMessage($existingConversation, $existing, false);
            }

            $conversationQuery = Conversation::query()->ownedBy($user)->forBot($lockedBot);
            if ($origin?->visitorSessionId !== null) {
                $conversationQuery->where('visitor_session_id', $origin->visitorSessionId);
            }
            $conversation = $conversationUuid
                ? $conversationQuery->where('uuid', $conversationUuid)->lockForUpdate()->firstOrFail()
                : $this->createConversation($user, $lockedBot, $body, $origin);
            if (in_array($conversation->status, [ConversationStatus::RESOLVED, ConversationStatus::ARCHIVED, ConversationStatus::SPAM], true)) {
                throw ValidationException::withMessages([
                    'conversation' => __('This conversation is closed and cannot accept new messages.'),
                ]);
            }

            $usesAi = $conversation->acceptsAiReplies();

            $message = new ConversationMessage;
            $message->uuid = (string) Str::uuid();
            $message->user_id = $user->id;
            $message->bot_id = $lockedBot->id;
            $message->conversation_id = $conversation->id;
            $message->fill([
                'actor_type' => MessageActor::VISITOR,
                'message_type' => ConversationMessageType::TEXT,
                'status' => $usesAi ? MessageStatus::QUEUED : MessageStatus::RECEIVED,
                'idempotency_key' => $idempotencyKey,
                'body' => trim($body),
            ]);
            $message->save();

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => Str::limit(Str::squish((string) $message->body), 500, ''),
                'last_message_sender_type' => MessageActor::VISITOR->value,
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            if ($usesAi) {
                $ledger = $this->usage->reserve($user, $lockedBot, $conversation, $message);
                GenerateConversationReply::dispatch($message->id, $conversation->id, $ledger->id, $user->id, $lockedBot->id)->afterCommit();
            }

            return new QueuedConversationMessage($conversation, $message, true);
        }, 3);
    }

    private function createConversation(User $user, Bot $bot, string $body, ?ConversationOrigin $origin): Conversation
    {
        $conversation = new Conversation;
        $conversation->uuid = (string) Str::uuid();
        $conversation->user_id = $user->id;
        $conversation->bot_id = $bot->id;
        $conversation->visitor_session_id = $origin?->visitorSessionId;
        $conversation->fill([
            'status' => ConversationStatus::OPEN_AI,
            'handling_mode' => ConversationHandlingMode::AI,
            'channel' => $origin?->channel ?? 'subscriber_api',
            'visitor_identifier' => $origin?->visitorIdentifier ?? 'subscriber-'.$user->id,
            'subject' => Str::limit(Str::squish($body), 180, ''),
            'started_at' => now('UTC'),
            'last_message_at' => now('UTC'),
            'unread_count' => 0,
        ]);
        $conversation->save();

        return $conversation;
    }
}
