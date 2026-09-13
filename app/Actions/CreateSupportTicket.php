<?php

namespace App\Actions;

use App\Enums\SupportTicketSenderType;
use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketCreated;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketAttachmentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class CreateSupportTicket
{
    public function __construct(private readonly SupportTicketAttachmentStore $attachmentStore) {}

    /**
     * @param  array{subject: string, priority: string, category: ?string, message: string}  $attributes
     * @param  array<int, UploadedFile>  $attachments
     */
    public function handle(User $subscriber, array $attributes, array $attachments): SupportTicket
    {
        $storedFiles = [];

        try {
            $ticket = DB::transaction(function () use ($subscriber, $attributes, $attachments, &$storedFiles): SupportTicket {
                $ticket = new SupportTicket;
                $ticket->reference = 'PENDING-'.Str::uuid();
                $ticket->requester_id = $subscriber->id;
                $ticket->subject = $attributes['subject'];
                $ticket->priority = $attributes['priority'];
                $ticket->category = $attributes['category'];
                $ticket->status = SupportTicketStatus::AWAITING_SUPPORT;
                $ticket->last_activity_at = now();
                $ticket->save();

                $ticket->reference = SupportTicket::referenceForId($ticket->id);
                $ticket->save();

                $message = $ticket->messages()->create([
                    'sender_id' => $subscriber->id,
                    'sender_type' => SupportTicketSenderType::SUBSCRIBER,
                    'body' => $attributes['message'],
                ]);

                $this->attachmentStore->store($message, $attachments, $storedFiles);

                return $ticket;
            });
        } catch (Throwable $exception) {
            $this->attachmentStore->cleanup($storedFiles);

            throw $exception;
        }

        try {
            SupportTicketCreated::dispatch($ticket);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $ticket;
    }
}
