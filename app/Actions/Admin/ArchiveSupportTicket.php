<?php

namespace App\Actions\Admin;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;

final class ArchiveSupportTicket
{
    public function handle(SupportTicket $ticket): void
    {
        DB::transaction(function () use ($ticket): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->archived_at === null) {
                $lockedTicket->archived_at = now();
                $lockedTicket->save();
            }
        });
    }
}
