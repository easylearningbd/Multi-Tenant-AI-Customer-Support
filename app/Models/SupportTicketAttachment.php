<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class SupportTicketAttachment extends Model
{
    protected $fillable = [
        'support_ticket_message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<SupportTicketMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }

    public function hasManagedPath(): bool
    {
        $path = str_replace('\\', '/', $this->path);
        $directory = trim((string) config('support-tickets.attachments.directory'), '/');

        return $this->disk === config('support-tickets.attachments.disk')
            && $directory !== ''
            && ! str_contains($path, '..')
            && Str::startsWith($path, $directory.'/');
    }
}
