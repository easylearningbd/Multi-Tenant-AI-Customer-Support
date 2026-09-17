<?php

namespace App\Services;

final class TextChunker
{
    /** @return list<array{index: int, content: string, token_count: int, checksum: string}> */
    public function chunk(string $text): array
    {
        $target = max(200, (int) config('neuraldesk.knowledge.chunk_target_characters', 2400));
        $overlap = min(max(0, (int) config('neuraldesk.knowledge.chunk_overlap_characters', 240)), $target - 1);
        $units = preg_split('/(?<=\.)\s+(?=[A-Z0-9])|\n{2,}/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $chunks = [];
        $buffer = '';

        foreach ($units as $unit) {
            $unit = trim($unit);
            if ($unit === '') {
                continue;
            }
            if ($buffer !== '' && mb_strlen($buffer."\n\n".$unit) > $target) {
                $chunks[] = $buffer;
                $buffer = $overlap > 0 ? mb_substr($buffer, -$overlap)."\n\n".$unit : $unit;
            } else {
                $buffer = $buffer === '' ? $unit : $buffer."\n\n".$unit;
            }
            while (mb_strlen($buffer) > $target * 2) {
                $chunks[] = mb_substr($buffer, 0, $target);
                $buffer = mb_substr($buffer, $target - $overlap);
            }
        }
        if (trim($buffer) !== '') {
            $chunks[] = trim($buffer);
        }

        return array_values(array_map(fn (string $content, int $index): array => [
            'index' => $index,
            'content' => $content,
            'token_count' => max(1, (int) ceil(mb_strlen($content) / 4)),
            'checksum' => hash('sha256', $content),
        ], array_values(array_unique($chunks)), array_keys(array_values(array_unique($chunks)))));
    }
}
