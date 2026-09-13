<?php

namespace App\Services;

use App\Models\SupportTicketMessage;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class SupportTicketAttachmentStore
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function store(SupportTicketMessage $message, array $files, array &$storedFiles): void
    {
        $disk = (string) config('support-tickets.attachments.disk');
        $directory = trim((string) config('support-tickets.attachments.directory'), '/')
            .'/'.$message->support_ticket_id.'/'.$message->id;

        foreach ($files as $file) {
            $extension = strtolower($file->extension());
            $path = $file->storeAs($directory, Str::uuid().'.'.$extension, $disk);

            if (! is_string($path)) {
                throw new RuntimeException('A support attachment could not be stored.');
            }

            $storedFiles[] = ['disk' => $disk, 'path' => $path];

            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));

            $message->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit($originalName, 255, ''),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }

    /** @param array<int, array{disk: string, path: string}> $storedFiles */
    public function cleanup(array $storedFiles): void
    {
        foreach ($storedFiles as $file) {
            $this->filesystems->disk($file['disk'])->delete($file['path']);
        }
    }
}
