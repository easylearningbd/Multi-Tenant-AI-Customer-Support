<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveBankTransferPayment;
use App\Actions\RejectBankTransferPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveBankTransferPaymentRequest;
use App\Http\Requests\Admin\ListPaymentsRequest;
use App\Http\Requests\Admin\RejectBankTransferPaymentRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Admin\PaymentIndexQuery;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

final class PaymentController extends Controller
{
    public function index(ListPaymentsRequest $request, PaymentIndexQuery $query): View
    {
        Gate::authorize('manageAny', Payment::class);

        return view('admin.payments.index', [
            'payments' => $query->paginate($request),
            'filters' => $request->validated(),
            'statuses' => PaymentStatus::cases(),
            'methods' => PaymentMethod::cases(),
            'plans' => Plan::query()->select(['id', 'name'])->ordered()->get(),
        ]);
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('manage', $payment);

        $payment->load([
            'user:id,name,email,avatar_path',
            'invoice:id,payment_id,number,status,subtotal_minor,total_minor,currency,description,issued_at,paid_at',
            'proof:id,payment_id,type,disk,path,original_name,mime_type,size,created_at',
            'reviewer:id,name,email',
            'subscription:id,user_id,plan_id,status,starts_at,current_period_starts_at,current_period_ends_at,provider',
        ]);

        return view('admin.payments.show', ['payment' => $payment]);
    }

    public function approve(
        ApproveBankTransferPaymentRequest $request,
        Payment $payment,
        ApproveBankTransferPayment $approvePayment,
    ): RedirectResponse {
        Gate::authorize('approve', $payment);
        $alreadyPaid = $payment->status === PaymentStatus::PAID;

        try {
            $approvePayment->handle(
                $payment,
                $request->user(),
                $request->boolean('amount_mismatch_acknowledged'),
                $request->validated('review_note'),
            );
        } catch (DomainException $exception) {
            return $this->domainFailure($payment, $exception);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.payments.show', $payment)->with('toast', [
                'type' => 'error',
                'title' => __('Approval failed'),
                'message' => __('The payment could not be approved safely. Please try again.'),
            ]);
        }

        return redirect()->route('admin.payments.show', $payment)->with('toast', [
            'type' => $alreadyPaid ? 'warning' : 'success',
            'title' => $alreadyPaid ? __('Payment already reviewed') : __('Payment approved'),
            'message' => $alreadyPaid
                ? __('This payment was already approved; no subscription period was added again.')
                : __('Payment approved and the selected plan is now active.'),
        ]);
    }

    public function reject(
        RejectBankTransferPaymentRequest $request,
        Payment $payment,
        RejectBankTransferPayment $rejectPayment,
    ): RedirectResponse {
        Gate::authorize('reject', $payment);
        $alreadyRejected = $payment->status === PaymentStatus::REJECTED;

        try {
            $rejectPayment->handle(
                $payment,
                $request->user(),
                $request->validated('rejection_reason'),
                $request->validated('review_note'),
            );
        } catch (DomainException $exception) {
            return $this->domainFailure($payment, $exception);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.payments.show', $payment)->with('toast', [
                'type' => 'error',
                'title' => __('Rejection failed'),
                'message' => __('The payment could not be rejected safely. Please try again.'),
            ]);
        }

        return redirect()->route('admin.payments.show', $payment)->with('toast', [
            'type' => $alreadyRejected ? 'warning' : 'success',
            'title' => $alreadyRejected ? __('Payment already reviewed') : __('Payment rejected'),
            'message' => $alreadyRejected
                ? __('This payment was already rejected; its original review remains unchanged.')
                : __('Payment rejected. The subscriber’s current plan was not changed.'),
        ]);
    }

    private function domainFailure(Payment $payment, DomainException $exception): RedirectResponse
    {
        $safeMessages = [
            'Only pending payments may be approved.' => __('Only pending payments may be approved.'),
            'Only pending payments may be rejected.' => __('Only pending payments may be rejected.'),
            'Only bank-transfer payments may be approved through this workflow.' => __('Only bank-transfer payments can use this approval workflow.'),
            'The submitted amount differs from the expected amount and requires explicit acknowledgement.' => __('The amount mismatch must be acknowledged before approval.'),
            'The payment currency or amount snapshot does not match its invoice.' => __('The payment currency or amount record is inconsistent and cannot be approved.'),
            'A payment proof is required before approval.' => __('A payment proof is required before approval.'),
            'The payment billing owner is unavailable.' => __('The payment owner is unavailable.'),
            'The referenced plan is no longer eligible for activation.' => __('The selected plan is no longer eligible for activation.'),
            'The payment invoice is unavailable.' => __('The related invoice is unavailable.'),
            'The pending payment already references a subscription.' => __('The payment already references a subscription and cannot be processed again.'),
            'The paid payment does not reference its activated subscription.' => __('The paid payment has an inconsistent subscription record and needs manual investigation.'),
            'Trial payments cannot be approved.' => __('Trial plans cannot be activated through a payment approval.'),
        ];

        return redirect()->route('admin.payments.show', $payment)->with('toast', [
            'type' => 'warning',
            'title' => __('Payment not changed'),
            'message' => $safeMessages[$exception->getMessage()] ?? __('The requested payment transition is not allowed.'),
        ]);
    }
}
