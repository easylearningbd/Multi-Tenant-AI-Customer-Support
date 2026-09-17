<?php

namespace App\Services;

use App\DTOs\EstimatedModelCost;

final class ModelCostEstimator
{
    public function estimate(string $model, int $inputTokens, int $outputTokens): ?EstimatedModelCost
    {
        $pricing = config('neuraldesk.ai.model_pricing.'.$model);
        if (! is_array($pricing)) {
            return null;
        }

        $inputRate = filter_var($pricing['input_per_million_minor'] ?? null, FILTER_VALIDATE_INT);
        $outputRate = filter_var($pricing['output_per_million_minor'] ?? null, FILTER_VALIDATE_INT);
        $currency = strtoupper((string) ($pricing['currency'] ?? ''));
        if ($inputRate === false || $outputRate === false || $inputRate < 0 || $outputRate < 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            return null;
        }

        $minor = (int) ceil(((max(0, $inputTokens) * $inputRate) + (max(0, $outputTokens) * $outputRate)) / 1_000_000);

        return new EstimatedModelCost($minor, $currency);
    }
}
