<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConversationMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'reply_to_uuid' => $this->whenLoaded('replyTo', fn () => $this->replyTo?->uuid),
            'actor' => $this->actor_type->value,
            'status' => $this->status->value,
            'body' => $this->body,
            'citations' => $this->whenLoaded('citations', fn () => $this->citations->map(fn ($citation): array => [
                'source_uuid' => $citation->source_uuid,
                'source_name' => $citation->source_name,
                'rank' => $citation->rank,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
