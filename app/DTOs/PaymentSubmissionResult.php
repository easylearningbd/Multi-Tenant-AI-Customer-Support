<?php

namespace App\DTOs;

use App\Models\Payment;

final readonly class PaymentSubmissionResult
{
    public function __construct(
        public Payment $payment,
        public bool $created,
    ) {}
}
