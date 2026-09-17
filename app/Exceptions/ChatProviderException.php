<?php

namespace App\Exceptions;

use RuntimeException;

final class ChatProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
