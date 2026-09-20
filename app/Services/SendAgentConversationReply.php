<?php

namespace App\Services;

use App\Enums\ConversationMessageType;
use App\Enums\ConversationStatus;
use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SendAgentConversationReply
{
    public function __construct(
        private readonly ConversationAttachmentStore $attachments,
        private readonly ConversationTransitionService $transitions,
    ) {}

    /** @param array<int, UploadedFile> $files */
    public function handle(Conversation $conversation, User $agent, ?string $body, string $idempotencyKey, array $files): ConversationMessage
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($conversation, $agent, $body, $idempotencyKey, $files, &$storedFiles): ConversationMessage {
                $locked = Conversation::query()->ownedBy($agent)->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                $existing = ConversationMessage::query()->forTenantBot($agent->id, $locked->bot_id)
                    ->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    abort_unless($existing->conversation_id === $locked->id, 404);

                    return $existing;
                }

                if (! $locked->acceptsAgentReplies()) {
                    throw ValidationException::withMessages([
                        'message' => $locked->status === ConversationStatus::OPEN_AI
                            ? __('Turn off automatic replies before sending a human reply.')
                            : __('This conversation is closed. Reopen it before replying.'),
                    ]);
                }

                if ($locked->status === ConversationStatus::NEEDS_HUMAN) {
                    $this->transitions->transition($locked, ConversationStatus::OPEN_MANUAL, $agent);
                    $locked->refresh();
                }

                $replyTo = ConversationMessage::query()
                    ->forTenantBot($agent->id, $locked->bot_id)
                    ->where('conversation_id', $locked->id)
                    ->where('actor_type', MessageActor::VISITOR)
                    ->whereNotIn('id', ConversationMessage::query()
                        ->select('reply_to_message_id')
                        ->where('conversation_id', $locked->id)
                        ->whereNotNull('reply_to_message_id'))
                    ->latest('id')
                    ->first();

                $message = new ConversationMessage;
                $message->uuid = (string) Str::uuid();
                $message->user_id = $agent->id;
                $message->bot_id = $locked->bot_id;
                $message->conversation_id = $locked->id;
                $message->fill([
                    'reply_to_message_id' => $replyTo?->id,
                    'sender_id' => $agent->id,
                    'actor_type' => MessageActor::AGENT,
                    'message_type' => trim((string) $body) === '' ? ConversationMessageType::ATTACHMENT : ConversationMessageType::TEXT,
                    'status' => MessageStatus::COMPLETED,
                    'idempotency_key' => $idempotencyKey,
                    'body' => trim((string) $body) !== '' ? trim((string) $body) : null,
                    'delivered_at' => now('UTC'),
                ]);
                $message->save();
                $message->setRelation('conversation', $locked);
                $this->attachments->store($message, $files, $storedFiles);

                $preview = trim((string) $message->body) !== ''
                    ? Str::limit(Str::squish((string) $message->body), 500, '')
                    : trans_choice('{1} :count attachment|[2,*] :count attachments', count($files), ['count' => count($files)]);
                $locked->forceFill([
                    'assigned_to' => $agent->id,
                    'last_message_at' => $message->created_at,
                    'last_message_preview' => $preview,
                    'last_message_sender_type' => MessageActor::AGENT->value,
                ])->save();

                return $message->load(['sender:id,name', 'attachments']);
            }, 3);
        } catch (Throwable $exception) {
            $this->attachments->cleanup($storedFiles);

            throw $exception;
        }
    }
}
