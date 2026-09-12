<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanInterval;
use App\Models\Plan;
use App\Services\DecimalMoney;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

abstract class PlanRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'planForm';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Plan::class, 'slug')->ignore($plan instanceof Plan ? $plan->id : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'features' => ['array', 'max:'.config('plans.max_features')],
            'features.*' => ['string', 'max:'.config('plans.max_feature_length')],
            'price' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'currency' => ['required', 'string', Rule::in(config('plans.currencies'))],
            'interval' => ['required', Rule::enum(PlanInterval::class)],
            'trial_days' => [
                Rule::excludeIf(fn (): bool => $this->input('interval') !== PlanInterval::TRIAL->value),
                'required',
                'integer',
                'between:1,365',
            ],
            'custom_pricing' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            ...collect(array_keys(Plan::LIMITS))
                ->mapWithKeys(fn (string $key): array => [
                    $key => ['required', 'integer', 'min:0', 'max:'.config('plans.max_limit')],
                ])
                ->all(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['price', 'interval'])) {
                return;
            }

            try {
                $priceMinor = DecimalMoney::toMinor((string) $this->input('price'));
            } catch (InvalidArgumentException) {
                return;
            }

            if ($this->input('interval') === PlanInterval::TRIAL->value && $priceMinor !== 0) {
                $validator->errors()->add('price', __('Trial plans must have a price of 0.00.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('features', []);

        if (is_string($features)) {
            $features = preg_split('/\R/u', $features) ?: [];
        }

        if (is_array($features)) {
            $unique = [];

            foreach ($features as $feature) {
                if (! is_string($feature) || ($feature = Str::squish($feature)) === '') {
                    continue;
                }

                $unique[Str::lower($feature)] ??= $feature;
            }

            $features = array_values($unique);
        }

        $this->merge([
            'name' => is_string($this->name) ? Str::squish($this->name) : $this->name,
            'slug' => is_string($this->slug) && trim($this->slug) !== '' ? Str::lower(trim($this->slug)) : null,
            'description' => is_string($this->description) && trim($this->description) !== '' ? trim($this->description) : null,
            'features' => $features,
            'price' => is_string($this->price) ? trim($this->price) : $this->price,
            'currency' => is_string($this->currency) ? Str::upper(trim($this->currency)) : $this->currency,
        ]);
    }
}
