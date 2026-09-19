<?php

namespace App\Rules;

use App\Services\WidgetOriginPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class WidgetOrigin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(WidgetOriginPolicy::class)->normalize($value) === null) {
            $fail(__('Each allowed origin must be a complete HTTP or HTTPS origin without a path, for example https://support.example.com.'));
        }
    }
}
