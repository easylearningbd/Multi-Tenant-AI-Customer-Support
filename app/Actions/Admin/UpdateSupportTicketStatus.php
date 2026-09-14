<?php

namespace App\Actions\Admin;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketStatusChangedNotification;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateSupportTicketStatus
{
    public function handle(SupportTicket $ticket, User $admin, SupportTicketStatus $nextStatus): bool
    {
        [$lockedTicket, $previousStatus, $changed] = DB::transaction(function () use ($ticket, $nextStatus): array {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->archived_at !== null) {
                throw new DomainException('Archived tickets cannot be updated.');
            }

            $previousStatus = $lockedTicket->status;
            if ($previousStatus === $nextStatus) {
                return [$lockedTicket, $previousStatus, false];
            }

            if (! $previousStatus->canTransitionTo($nextStatus)) {
                throw new DomainException('This ticket status transition is not allowed.');
            }

            $lockedTicket->status = $nextStatus;
            $lockedTicket->resolved_at = $nextStatus === SupportTicketStatus::RESOLVED ? now() : null;
            $lockedTicket->closed_at = $nextStatus === SupportTicketStatus::CLOSED ? now() : null;
            $lockedTicket->last_activity_at = now();
            $lockedTicket->save();

            return [$lockedTicket, $previousStatus, true];
        });

        if ($changed && $lockedTicket->requester) {
            try {
                $lockedTicket->requester->notify(new SupportTicketStatusChangedNotification(
                    $lockedTicket,
                    $previousStatus,
                    $admin,
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $changed;
    }
}
