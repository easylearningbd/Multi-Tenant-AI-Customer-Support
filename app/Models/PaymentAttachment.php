<?php

namespace App\Models;

use App\Enums\PaymentAttachmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class PaymentAttachment extends Model
{
    protected $fillable = ['payment_id', 'type', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    protected function casts(): array
    {
        return [
            'type' => PaymentAttachmentType::class,
            'size' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function hasManagedPath(): bool
    {
        $path = str_replace('\\', '/', $this->path);
        $directory = trim((string) config('billing.bank_transfer.proofs.directory'), '/');

        return $this->disk === config('billing.bank_transfer.proofs.disk')
            && $directory !== ''
            && ! str_contains($path, '..')
            && Str::startsWith($path, $directory.'/');
    }
}
