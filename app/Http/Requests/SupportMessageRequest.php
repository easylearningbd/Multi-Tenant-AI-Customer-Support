<?php

namespace App\Http\Requests;

use App\Rules\MeaningfulSupportMessage;
use App\Rules\SafeSupportAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

abstract class SupportMessageRequest extends FormRequest
{
    /** @return array<int, mixed> */
    protected function messageRules(int $minimum = 2): array
    {
        return ['required', 'string', "min:{$minimum}", 'max:10000', new MeaningfulSupportMessage];
    }

    /** @return array<string, array<int, mixed>> */
    protected function attachmentRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.config('support-tickets.attachments.max_files')],
            'attachments.*' => [
                'file',
                File::types(['pdf', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'])
                    ->max(config('support-tickets.attachments.max_kilobytes')),
                new SafeSupportAttachment,
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->message)) {
            $this->merge(['message' => trim($this->message)]);
        }
    }
}
