<?php

namespace App\Contracts;

use App\DTOs\ChatCompletionRequest;
use App\DTOs\ChatCompletionResult;

interface ChatCompletionProviderInterface
{
    public function respond(ChatCompletionRequest $request): ChatCompletionResult;
}
