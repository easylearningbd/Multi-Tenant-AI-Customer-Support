<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdateSupportTicketStatus;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSupportTicketStatusRequest;
use App\Models\SupportTicket;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Throwable;

final class SupportTicketStatusController extends Controller
{
    public function __invoke(
        UpdateSupportTicketStatusRequest $request,
        SupportTicket $adminTicket,
        UpdateSupportTicketStatus $updateStatus,
    ): RedirectResponse {
        try {
            $changed = $updateStatus->handle(
                $adminTicket,
                $request->user(),
                SupportTicketStatus::from($request->validated('status')),
            );
        } catch (DomainException) {
            return redirect()->route('admin.support-tickets.show', $adminTicket)->with('toast', [
                'type' => 'warning',
                'title' => __('Status not changed'),
                'message' => __('This ticket status transition is not allowed.'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.support-tickets.show', $adminTicket)->with('toast', [
                'type' => 'error',
                'title' => __('Status update failed'),
                'message' => __('The ticket status could not be updated. Please try again.'),
            ]);
        }

        return redirect()->route('admin.support-tickets.show', $adminTicket)->with('toast', [
            'type' => 'success',
            'title' => $changed ? __('Status updated') : __('No changes'),
            'message' => $changed ? __('Ticket status updated successfully.') : __('The ticket already has that status.'),
        ]);
    }
}
