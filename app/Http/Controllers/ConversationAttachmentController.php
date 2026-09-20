<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ConversationAttachmentController extends Controller
{
    public function __invoke(Conversation $subscriberConversation, string $attachment): StreamedResponse
    {
        Gate::authorize('downloadAttachment', $subscriberConversation);
        $file = ConversationAttachment::query()
            ->forTenantConversation($subscriberConversation->user_id, $subscriberConversation->bot_id, $subscriberConversation->id)
            ->where('uuid', $attachment)
            ->firstOrFail();

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
