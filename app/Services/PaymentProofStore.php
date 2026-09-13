<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class PaymentProofStore
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    /** @return array{disk: string, path: string, original_name: string, mime_type: string, size: int} */
    public function store(UploadedFile $proof, int $billingOwnerId): array
    {
        $disk = (string) config('billing.bank_transfer.proofs.disk');
        $directory = trim((string) config('billing.bank_transfer.proofs.directory'), '/')
            .'/'.$billingOwnerId;
        $mimeType = strtolower((string) $proof->getMimeType());
        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('The payment proof type is not supported.'),
        };
        $path = $proof->storeAs($directory, Str::uuid().'.'.$extension, $disk);

        if (! is_string($path)) {
            throw new RuntimeException('The payment proof could not be stored.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(basename(str_replace('\\', '/', $proof->getClientOriginalName())), 255, ''),
            'mime_type' => $mimeType,
            'size' => (int) $proof->getSize(),
        ];
    }

    /** @param array{disk: string, path: string}|null $stored */
    public function cleanup(?array $stored): void
    {
        if ($stored) {
            $this->filesystems->disk($stored['disk'])->delete($stored['path']);
        }
    }
}
