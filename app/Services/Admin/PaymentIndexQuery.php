<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\ListPaymentsRequest;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaymentIndexQuery
{
    public function paginate(ListPaymentsRequest $request): LengthAwarePaginator
    {
        $filters = $request->validated();
        $query = Payment::query()
            ->select([
                'id', 'reference', 'user_id', 'plan_id', 'payment_method', 'status',
                'expected_amount_minor', 'submitted_amount_minor', 'currency',
                'plan_name_snapshot', 'transaction_reference', 'submitted_at', 'created_at',
            ])
            ->with([
                'user:id,name,email,avatar_path',
                'invoice:id,payment_id,number',
            ]);

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search): void {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('plan_name_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('number', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($method = $filters['payment_method'] ?? null) {
            $query->where('payment_method', $method);
        }

        if ($planId = $filters['plan_id'] ?? null) {
            $query->where('plan_id', $planId);
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->where('submitted_at', '>=', $dateFrom.' 00:00:00');
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->where('submitted_at', '<=', $dateTo.' 23:59:59');
        }

        $sort = $filters['sort'] ?? 'submitted_at';
        $direction = $filters['direction'] ?? 'desc';

        return $query->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }
}
