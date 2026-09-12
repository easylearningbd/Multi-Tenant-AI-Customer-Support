<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class UpdateSubscriberPassword
{
    public function handle(User $subscriber, string $password): void
    {
        $subscriber->password = Hash::make($password);
        $subscriber->save();
    }
}
