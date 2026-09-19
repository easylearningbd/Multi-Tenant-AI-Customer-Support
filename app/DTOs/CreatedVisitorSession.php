<?php

namespace App\DTOs;

use App\Models\VisitorSession;

final readonly class CreatedVisitorSession
{
    public function __construct(
        public VisitorSession $session,
        public string $token,
    ) {}
}
