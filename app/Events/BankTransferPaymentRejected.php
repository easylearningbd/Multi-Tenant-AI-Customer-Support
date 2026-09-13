<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BankTransferPaymentRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}
