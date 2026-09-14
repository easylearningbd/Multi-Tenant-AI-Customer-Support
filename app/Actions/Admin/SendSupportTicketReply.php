<?php

namespace App\Actions\Admin;

use App\Enums\SupportTicketSenderType;
use App\Enums\SupportTicketStatus;
use App\Exceptions\SupportTicketNotReplyable;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportTicketAdminReplied;
use App\Services\SupportTicketAttachmentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SendSupportTicketReply
{
    public function __construct(private readonly SupportTicketAttachmentStore $attachmentStore) {}

    /** @param array<int, UploadedFile> $attachments */
    public function handle(
        SupportTicket $ticket,
        User $admin,
        string $body,
        array $attachments,
        string $submissionToken,
    ): SupportTicketMessage {
        $storedFiles = [];
        $wasCreated = false;

        try {
            [$lockedTicket, $message] = DB::transaction(function () use (
                $ticket,
                $admin,
                $body,
                $attachments,
                $submissionToken,
                &$storedFiles,
                &$wasCreated,
            ): array {
                $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);

                $existing = $lockedTicket->messages()
                    ->where('submission_token', $submissionToken)
                    ->where('sender_id', $admin->id)
                    ->first();

                if ($existing) {
                    return [$lockedTicket, $existing];
                }

                if ($lockedTicket->archived_at !== null || ! $lockedTicket->status->acceptsAdminReplies()) {
                    throw new SupportTicketNotReplyable('Resolved, closed, or archived tickets cannot receive replies.');
                }

                $message = $lockedTicket->messages()->create([
                    'sender_id' => $admin->id,
                    'sender_type' => SupportTicketSenderType::STAFF,
                    'submission_token' => $submissionToken,
                    'body' => $body,
                ]);

                $this->attachmentStore->store($message, $attachments, $storedFiles);

                $lockedTicket->status = SupportTicketStatus::AWAITING_USER;
                $lockedTicket->last_activity_at = now();
                $lockedTicket->resolved_at = null;
                $lockedTicket->closed_at = null;
                $lockedTicket->save();
                $wasCreated = true;

                return [$lockedTicket, $message];
            });
        } catch (Throwable $exception) {
            $this->attachmentStore->cleanup($storedFiles);

            throw $exception;
        }

        if ($wasCreated && $lockedTicket->requester) {
            try {
                $lockedTicket->requester->notify(new SupportTicketAdminReplied($lockedTicket, $message, $admin));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $message;
    }
}
