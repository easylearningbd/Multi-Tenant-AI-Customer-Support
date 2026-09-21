<?php

namespace App\Exceptions;

use App\Enums\PlanMetric;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PlanLimitExceededException extends ValidationException
{
    public function __construct(
        public readonly PlanMetric $metric,
        public readonly int $current,
        public readonly int $limit,
    ) {
        parent::__construct(Validator::make([], []));
        $this->message = $this->messageForMetric();
        $this->validator->errors()->add('plan_limit', $this->message);
        $this->status = 422;
    }

    private function messageForMetric(): string
    {
        if ($this->metric === PlanMetric::STORAGE) {
            return __('This upload would exceed your :limit MB knowledge-storage limit. You currently use :used MB.', [
                'limit' => $this->formatBytes($this->limit),
                'used' => $this->formatBytes($this->current),
            ]);
        }

        return match ($this->metric) {
            PlanMetric::AI_ANSWERS => __('You have reached your monthly limit of :limit AI answers.', ['limit' => number_format($this->limit)]),
            PlanMetric::CHATBOTS => __('Your current plan allows :limit chatbot(s).', ['limit' => number_format($this->limit)]),
            PlanMetric::KNOWLEDGE_BASES => __('Your current plan allows :limit knowledge base(s).', ['limit' => number_format($this->limit)]),
            PlanMetric::KNOWLEDGE_SOURCES => __('Your current plan allows :limit knowledge source(s).', ['limit' => number_format($this->limit)]),
            PlanMetric::TEAM_MEMBERS => __('Your current plan allows :limit team member(s).', ['limit' => number_format($this->limit)]),
            PlanMetric::STORAGE => '',
        };
    }

    private function formatBytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 2, '.', ''), '0'), '.');
    }
}
