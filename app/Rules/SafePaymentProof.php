<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafePaymentProof implements ValidationRule
{
    /** @var array<string, list<string>> */
    private const MIME_EXTENSIONS = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    private const DANGEROUS_EXTENSIONS = [
        'bat', 'cgi', 'cmd', 'com', 'exe', 'htm', 'html', 'js', 'mjs', 'phar', 'php',
        'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pl', 'ps1', 'py', 'sh', 'svg',
        'vbs', 'xml', 'zip', 'rar', '7z',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(__('The payment proof must be a valid uploaded file.'));

            return;
        }

        $originalName = str_replace('\\', '/', $value->getClientOriginalName());
        $fileName = basename($originalName);
        $segments = array_map('strtolower', explode('.', $fileName));
        $extension = count($segments) > 1 ? array_pop($segments) : '';
        $mimeType = strtolower((string) $value->getMimeType());

        if ($fileName !== $originalName || preg_match('/[\x00-\x1F\x7F]/', $fileName)) {
            $fail(__('The payment proof filename is invalid.'));

            return;
        }

        if (array_intersect($segments, self::DANGEROUS_EXTENSIONS) !== []) {
            $fail(__('The payment proof file type is not allowed.'));

            return;
        }

        if (! isset(self::MIME_EXTENSIONS[$mimeType])
            || ! in_array($extension, self::MIME_EXTENSIONS[$mimeType], true)) {
            $fail(__('The payment proof content and file extension must be a PDF, JPG, PNG, or WebP file.'));
        }
    }
}
