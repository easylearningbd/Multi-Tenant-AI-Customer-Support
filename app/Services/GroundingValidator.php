<?php

namespace App\Services;

use App\DTOs\RagRetrievalResult;

final class GroundingValidator
{
    public function hasValidCitation(string $answer, RagRetrievalResult $retrieval): bool
    {
        if ($retrieval->matches === []) {
            return false;
        }

        preg_match_all('/\[source:([^\]\s]+)\]/', $answer, $matches);
        $allowed = array_map(fn ($match): string => $match->sourceUuid, $retrieval->matches);

        foreach ($matches[1] ?? [] as $uuid) {
            if (is_string($uuid) && in_array($uuid, $allowed, true)) {
                return true;
            }
        }

        return false;
    }
}
