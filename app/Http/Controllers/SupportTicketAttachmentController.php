<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SupportTicketAttachmentController extends Controller
{
    public function download(SupportTicket $subscriberTicket, int $attachment): StreamedResponse
    {
        $attachmentRecord = SupportTicketAttachment::query()
            ->with('message:id,support_ticket_id')
            ->whereKey($attachment)
            ->whereHas('message', fn ($query) => $query->where('support_ticket_id', $subscriberTicket->id))
            ->firstOrFail();

        Gate::authorize('downloadAttachment', [$subscriberTicket, $attachmentRecord]);

        abort_unless(
            $attachmentRecord->hasManagedPath()
                && Storage::disk($attachmentRecord->disk)->exists($attachmentRecord->path),
            404,
        );

        return Storage::disk($attachmentRecord->disk)->download(
            $attachmentRecord->path,
            $attachmentRecord->original_name,
            [
                'Content-Type' => $attachmentRecord->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
