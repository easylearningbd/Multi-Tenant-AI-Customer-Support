<?php

namespace App\Http\Requests;

use App\Models\Bot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use ZipArchive;

final class UploadKnowledgeFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bot = $this->route('subscriberBot');

        return $bot instanceof Bot && $this->user()?->can('train', $bot) === true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'max:'.config('neuraldesk.knowledge.max_file_kb'), 'mimes:txt,md,csv,pdf,docx'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = config('neuraldesk.knowledge.allowed_files', []);
            foreach ($this->file('files', []) as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $mime = strtolower((string) $file->getMimeType());
                if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
                    $validator->errors()->add('files', __('A file type or content signature is not supported.'));
                }
                if ($extension === 'docx') {
                    $zip = new ZipArchive;
                    $opened = $zip->open($file->getRealPath()) === true;
                    if (! $opened || $zip->locateName('word/document.xml') === false) {
                        $validator->errors()->add('files', __('A DOCX file is not a valid Office Open XML document.'));
                    }
                    if ($opened) {
                        $zip->close();
                    }
                }
            }
        }];
    }
}
