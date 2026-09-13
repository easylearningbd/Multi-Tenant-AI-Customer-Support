<?php

namespace App\Services;

final class BankTransferConfiguration
{
    public function isComplete(): bool
    {
        return (bool) config('billing.bank_transfer.enabled')
            && $this->requiredValue('bank_name') !== null
            && $this->requiredValue('account_name') !== null
            && $this->requiredValue('account_number') !== null
            && $this->currency() !== null;
    }

    public function currency(): ?string
    {
        $currency = strtoupper(trim((string) config('billing.bank_transfer.currency')));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    /** @return array<string, string> */
    public function displayDetails(): array
    {
        $labels = [
            'bank_name' => __('Bank name'),
            'account_name' => __('Account name'),
            'account_number' => __('Account number'),
            'branch_name' => __('Branch'),
            'routing_number' => __('Routing number'),
            'swift_code' => __('SWIFT code'),
            'iban' => __('IBAN'),
        ];

        $details = [];

        foreach ($labels as $key => $label) {
            $value = $this->requiredValue($key);

            if ($value !== null) {
                $details[$label] = $value;
            }
        }

        return $details;
    }

    public function instructions(): ?string
    {
        return $this->requiredValue('instructions');
    }

    private function requiredValue(string $key): ?string
    {
        $value = trim((string) config("billing.bank_transfer.{$key}"));

        return $value !== '' ? $value : null;
    }
}
