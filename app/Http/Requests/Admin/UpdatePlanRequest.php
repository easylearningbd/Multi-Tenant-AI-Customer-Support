<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;

final class UpdatePlanRequest extends PlanRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && $this->user()?->can('update', $plan) === true;
    }
}
