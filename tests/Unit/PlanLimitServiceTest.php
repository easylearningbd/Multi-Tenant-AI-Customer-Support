<?php

use App\Models\Plan;
use App\Services\PlanLimitService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('positive plan limits permit usage up to the configured maximum', function () {
    $plan = new Plan(['limits' => planLimits(['chatbots_limit' => 3])]);
    $service = new PlanLimitService;

    expect($service->allows($plan, 'chatbots_limit', 2))->toBeTrue()
        ->and($service->allows($plan, 'chatbots_limit', 3))->toBeFalse()
        ->and($service->allows($plan, 'chatbots_limit', 1, 2))->toBeTrue();
});

test('zero plan limits are unlimited through the centralized service', function () {
    $plan = new Plan(['limits' => planLimits(['ai_answers_per_month' => 0])]);
    $service = new PlanLimitService;

    expect($plan->hasUnlimitedLimit('ai_answers_per_month'))->toBeTrue()
        ->and($service->allows($plan, 'ai_answers_per_month', PHP_INT_MAX - 1))->toBeTrue();
});

test('limit enforcement returns a user-safe validation error', function () {
    $plan = new Plan(['limits' => planLimits(['knowledge_bases_limit' => 1])]);

    expect(fn () => (new PlanLimitService)->ensureAllows($plan, 'knowledge_bases_limit', 1))
        ->toThrow(ValidationException::class, 'Your current plan allows 1 knowledge base(s).');
});

function planLimits(array $overrides = []): array
{
    return array_replace(array_fill_keys(array_keys(Plan::LIMITS), 0), $overrides);
}
