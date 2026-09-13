<?php

namespace App\Actions;

use App\Enums\SupportTicketSenderType;
use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketReplied;
use App\Exceptions\SupportTicketNotReplyable;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\SupportTicketAttachmentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReplyToSupportTicket
{
    public function __construct(private readonly SupportTicketAttachmentStore $attachmentStore) {}

    /** @param array<int, UploadedFile> $attachments */
    public function handle(SupportTicket $ticket, User $subscriber, string $body, array $attachments): SupportTicketMessage
    {
        $storedFiles = [];

        try {
            [$lockedTicket, $message] = DB::transaction(function () use ($ticket, $subscriber, $body, $attachments, &$storedFiles): array {
                $lockedTicket = SupportTicket::query()
                    ->ownedBy($subscriber)
                    ->lockForUpdate()
                    ->findOrFail($ticket->id);

                if (! $lockedTicket->status->acceptsSubscriberReplies()) {
                    throw new SupportTicketNotReplyable('Closed tickets cannot receive replies.');
                }

                $message = $lockedTicket->messages()->create([
                    'sender_id' => $subscriber->id,
                    'sender_type' => SupportTicketSenderType::SUBSCRIBER,
                    'body' => $body,
                ]);

                $this->attachmentStore->store($message, $attachments, $storedFiles);

                $lockedTicket->status = SupportTicketStatus::AWAITING_SUPPORT;
                $lockedTicket->last_activity_at = now();
                $lockedTicket->save();

                return [$lockedTicket, $message];
            });
        } catch (Throwable $exception) {
            $this->attachmentStore->cleanup($storedFiles);

            throw $exception;
        }

        try {
            SupportTicketReplied::dispatch($lockedTicket, $message);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $message;
    }
}
