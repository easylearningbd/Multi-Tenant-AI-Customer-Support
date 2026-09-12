<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePlanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && $this->user()?->can('update', $plan) === true;
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
