<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\ListSupportTicketsRequest;
use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SupportTicketIndexQuery
{
    public function paginate(ListSupportTicketsRequest $request): LengthAwarePaginator
    {
        $filters = $request->validated();
        $query = SupportTicket::query()
            ->select(['id', 'reference', 'requester_id', 'subject', 'priority', 'category', 'status', 'last_activity_at', 'archived_at'])
            ->with('requester:id,name,email,avatar_path');

        match ($filters['visibility'] ?? 'active') {
            'archived' => $query->archived(),
            'all' => null,
            default => $query->notArchived(),
        };

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search): void {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn ($requester) => $requester
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($priority = $filters['priority'] ?? null) {
            $query->where('priority', $priority);
        }

        $sort = $filters['sort'] ?? 'last_activity_at';
        $direction = $filters['direction'] ?? 'desc';

        return $query->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }
}
