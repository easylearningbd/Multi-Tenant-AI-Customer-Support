<?php

namespace App\Services;

final class AiReplySanitizer
{
    public function sanitize(string $answer): string
    {
        $answer = str_replace(["\r\n", "\r"], "\n", $answer);
        $answer = preg_replace('/[ \t]*\[source:[^\]\r\n]*\]/iu', '', $answer) ?? $answer;
        $answer = preg_replace('/[ \t]+\n/u', "\n", $answer) ?? $answer;
        $answer = preg_replace('/\n{3,}/u', "\n\n", $answer) ?? $answer;

        return trim($answer);
    }
}
