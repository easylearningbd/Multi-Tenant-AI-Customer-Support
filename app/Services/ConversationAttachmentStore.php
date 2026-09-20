<?php

namespace App\Services;

use App\Models\ConversationMessage;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class ConversationAttachmentStore
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function store(ConversationMessage $message, array $files, array &$storedFiles): void
    {
        $disk = (string) config('neuraldesk.conversations.attachment_disk', 'conversation_attachments');
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $this->filesystems->disk($disk)->putFileAs('', $file, Str::uuid().'.'.$extension);
            if (! is_string($path)) {
                throw new RuntimeException('A conversation attachment could not be stored.');
            }

            $storedFiles[] = ['disk' => $disk, 'path' => $path];
            $attachment = $message->attachments()->make([
                'disk' => $disk,
                'path' => $path,
                'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 255, ''),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
            ]);
            $attachment->uuid = (string) Str::uuid();
            $attachment->user_id = $message->user_id;
            $attachment->bot_id = $message->bot_id;
            $attachment->conversation_id = $message->conversation_id;
            $attachment->save();
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
