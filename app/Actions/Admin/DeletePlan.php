<?php

namespace App\Actions\Admin;

use App\Models\Plan;
use App\Services\PlanDeletionGuard;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeletePlan
{
    public function __construct(private readonly PlanDeletionGuard $guard) {}

    public function handle(Plan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $lockedPlan = Plan::query()->lockForUpdate()->findOrFail($plan->id);

            if ($this->guard->isReferenced($lockedPlan)) {
                throw new DomainException('Referenced plans cannot be deleted.');
            }

            $lockedPlan->delete();
        });
    }
}
