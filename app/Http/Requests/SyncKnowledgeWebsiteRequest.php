<?php

namespace App\Http\Requests;

use App\Exceptions\UnsafeUrlException;
use App\Models\Bot;
use App\Services\UrlSafetyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SyncKnowledgeWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bot = $this->route('subscriberBot');

        return $bot instanceof Bot && $this->user()?->can('train', $bot) === true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in(['website', 'sitemap'])],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'page_limit' => ['required', 'integer', 'min:1', 'max:'.config('neuraldesk.knowledge.website.maximum_page_limit')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('url')) {
                return;
            }
            try {
                app(UrlSafetyService::class)->assertSafe((string) $this->input('url'));
            } catch (UnsafeUrlException $exception) {
                $validator->errors()->add('url', $exception->getMessage());
            }
        }];
    }
}
