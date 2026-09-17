<?php

namespace App\Services;

use App\Exceptions\KnowledgeExtractionException;

final class TextNormalizer
{
    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $paragraphs = preg_split('/\n{2,}/', $text) ?: [];
        $paragraphs = array_values(array_filter(array_map(function (string $paragraph): string {
            $lines = preg_split('/\n/', $paragraph) ?: [];

            return trim(implode("\n", array_map(
                fn (string $line): string => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? ''),
                $lines,
            )));
        }, $paragraphs)));
        $normalized = trim(implode("\n\n", $paragraphs));

        if ($normalized === '') {
            throw new KnowledgeExtractionException('The knowledge source did not contain readable text.');
        }

        return $normalized;
    }
}
