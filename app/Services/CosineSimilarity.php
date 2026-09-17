<?php

namespace App\Services;

use InvalidArgumentException;

final class CosineSimilarity
{
    /** @param array<mixed> $left @param array<mixed> $right */
    public function score(array $left, array $right, ?float $rightNorm = null): float
    {
        $left = $this->validatedVector($left);
        $right = $this->validatedVector($right);
        if (count($left) !== count($right)) {
            throw new InvalidArgumentException('Embedding dimensions do not match.');
        }

        $dot = $leftSquares = $rightSquares = 0.0;
        foreach ($left as $index => $value) {
            $dot += $value * $right[$index];
            $leftSquares += $value * $value;
            if ($rightNorm === null) {
                $rightSquares += $right[$index] * $right[$index];
            }
        }

        $leftNorm = sqrt($leftSquares);
        $rightNorm ??= sqrt($rightSquares);
        if ($leftNorm <= 0.0 || ! is_finite($leftNorm) || $rightNorm <= 0.0 || ! is_finite($rightNorm)) {
            throw new InvalidArgumentException('Embedding vectors must have a finite non-zero norm.');
        }

        $score = $dot / ($leftNorm * $rightNorm);
        if (! is_finite($score)) {
            throw new InvalidArgumentException('Cosine similarity is not finite.');
        }

        return max(-1.0, min(1.0, $score));
    }

    /** @param array<mixed> $vector @return list<float> */
    public function validatedVector(array $vector): array
    {
        if ($vector === []) {
            throw new InvalidArgumentException('Embedding vector cannot be empty.');
        }

        return array_values(array_map(function (mixed $value): float {
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException('Embedding vectors must contain only numeric values.');
            }
            $value = (float) $value;
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Embedding vectors must contain only finite values.');
            }

            return $value;
        }, $vector));
    }

    /** @param array<mixed> $vector */
    public function norm(array $vector): float
    {
        $vector = $this->validatedVector($vector);
        $norm = sqrt(array_sum(array_map(fn (float $value): float => $value * $value, $vector)));
        if ($norm <= 0.0 || ! is_finite($norm)) {
            throw new InvalidArgumentException('Embedding vectors must have a finite non-zero norm.');
        }

        return $norm;
    }
}
