<?php

namespace App\Listeners;

use App\Actions\AssignDefaultTrial;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

final class AssignDefaultTrialToRegisteredSubscriber
{
    public function __construct(private readonly AssignDefaultTrial $assignDefaultTrial) {}

    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->assignDefaultTrial->handle($event->user);
        }
    }
}
