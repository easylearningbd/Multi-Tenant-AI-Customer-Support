<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafeConversationAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(__('The :attribute must be a valid uploaded file.', ['attribute' => $attribute]));

            return;
        }

        $originalName = str_replace('\\', '/', $value->getClientOriginalName());
        $fileName = basename($originalName);
        $segments = array_map('strtolower', explode('.', $fileName));
        $extension = count($segments) > 1 ? (string) array_pop($segments) : '';
        $allowed = (array) config('neuraldesk.conversations.allowed_attachments', []);
        $mime = (string) ($value->getMimeType() ?: 'application/octet-stream');
        $dangerous = ['bat', 'cmd', 'com', 'exe', 'html', 'htm', 'js', 'mjs', 'phar', 'php', 'phtml', 'ps1', 'py', 'sh', 'svg', 'vbs'];

        if ($fileName !== $originalName || preg_match('/[\x00-\x1F\x7F]/', $fileName)) {
            $fail(__('The :attribute filename is invalid.', ['attribute' => $attribute]));

            return;
        }

        if (! isset($allowed[$extension])
            || ! in_array($mime, (array) $allowed[$extension], true)
            || array_intersect($segments, $dangerous) !== []) {
            $fail(__('The :attribute file type is not allowed.', ['attribute' => $attribute]));
        }
    }
}
