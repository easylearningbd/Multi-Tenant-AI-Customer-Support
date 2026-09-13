<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafeSupportAttachment implements ValidationRule
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];

    private const DANGEROUS_EXTENSIONS = [
        'bat', 'cgi', 'cmd', 'com', 'exe', 'htm', 'html', 'js', 'mjs', 'phar', 'php',
        'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pl', 'ps1', 'py', 'sh', 'svg',
        'vbs', 'xml', 'zip', 'rar', '7z',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(__('The :attribute must be a valid uploaded file.', ['attribute' => $attribute]));

            return;
        }

        $originalName = str_replace('\\', '/', $value->getClientOriginalName());
        $fileName = basename($originalName);
        $segments = array_map('strtolower', explode('.', $fileName));
        $extension = count($segments) > 1 ? array_pop($segments) : '';

        if ($fileName !== $originalName || preg_match('/[\x00-\x1F\x7F]/', $fileName)) {
            $fail(__('The :attribute filename is invalid.', ['attribute' => $attribute]));

            return;
        }

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)
            || array_intersect($segments, self::DANGEROUS_EXTENSIONS) !== []) {
            $fail(__('The :attribute file type is not allowed.', ['attribute' => $attribute]));
        }
    }
}
