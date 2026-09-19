<?php

namespace App\DTOs;

final readonly class ConversationOrigin
{
    public function __construct(
        public string $channel,
        public string $visitorIdentifier,
        public ?int $visitorSessionId = null,
    ) {}
}
