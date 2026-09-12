<?php

namespace App\Services;

use InvalidArgumentException;

final class DecimalMoney
{
    public static function toMinor(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('The amount must be a non-negative decimal with no more than two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function fromMinor(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
