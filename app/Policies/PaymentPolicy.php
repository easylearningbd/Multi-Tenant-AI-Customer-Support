<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

final class PaymentPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::USER && $user->isBillingOwner();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->create($user) && $payment->user_id === $user->id;
    }

    public function downloadProof(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
