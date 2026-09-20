<?php

namespace App\Http\Resources;

use App\Enums\MessageActor;
use App\Models\Conversation;
use App\Services\AiReplySanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConversationMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $body = (string) $this->body;
        if ($this->actor_type === MessageActor::AI) {
            $body = (new AiReplySanitizer)->sanitize($body);
        }

        $conversationRouteValue = $request->route('subscriberConversation');
        $conversationUuid = $conversationRouteValue instanceof Conversation
            ? $conversationRouteValue->uuid
            : (string) $conversationRouteValue;
        $isSubscriberInbox = $request->routeIs('conversations.*');

        return [
            'uuid' => $this->uuid,
            'reply_to_uuid' => $this->whenLoaded('replyTo', fn () => $this->replyTo?->uuid),
            'actor' => $this->actor_type->value,
            'sender_name' => $this->whenLoaded('sender', fn () => $this->sender?->name),
            'message_type' => $this->message_type?->value ?? 'text',
            'status' => $this->status->value,
            'body' => $body,
            'citations' => $this->whenLoaded('citations', fn () => $this->citations->map(fn ($citation): array => [
                'source_uuid' => $citation->source_uuid,
                'source_name' => $citation->source_name,
                'rank' => $citation->rank,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'attachments' => $this->when(
                $isSubscriberInbox && $this->relationLoaded('attachments'),
                fn () => $this->attachments->map(fn ($attachment): array => [
                    'uuid' => $attachment->uuid,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'download_url' => route('conversations.attachments.download', [$conversationUuid, $attachment->uuid]),
                ])->values(),
            ),
        ];
    }
}
