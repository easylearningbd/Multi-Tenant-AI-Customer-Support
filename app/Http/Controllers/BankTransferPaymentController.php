<?php

namespace App\Http\Controllers;

use App\Actions\SubmitBankTransferPayment;
use App\Exceptions\BankTransferUnavailable;
use App\Http\Requests\StoreBankTransferPaymentRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\BankTransferConfiguration;
use App\Services\BankTransferPlanEligibility;
use App\Services\CurrentSubscriptionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

final class BankTransferPaymentController extends Controller
{
    public function create(
        Request $request,
        Plan $plan,
        BankTransferPlanEligibility $eligibility,
        BankTransferConfiguration $configuration,
        CurrentSubscriptionResolver $subscriptions,
    ): View|RedirectResponse {
        /** @var User $billingOwner */
        $billingOwner = $request->user();
        Gate::authorize('create', Payment::class);

        try {
            $eligibility->assertEligible($billingOwner, $plan);
        } catch (BankTransferUnavailable) {
            return redirect()->route('billing.index')->with('toast', [
                'type' => 'warning',
                'title' => __('Payment unavailable'),
                'message' => __('This plan cannot currently be purchased by bank transfer.'),
            ]);
        }

        return view('billing.payments.create', [
            'plan' => $plan,
            'bankDetails' => $configuration->displayDetails(),
            'bankInstructions' => $configuration->instructions(),
            'subscriberPlanName' => $subscriptions->for($billingOwner)?->planName() ?? __('No active plan'),
        ]);
    }

    public function store(
        StoreBankTransferPaymentRequest $request,
        Plan $plan,
        SubmitBankTransferPayment $submitPayment,
    ): RedirectResponse {
        Gate::authorize('create', Payment::class);
        $attributes = $request->safe()->only([
            'payer_name', 'payer_bank_name', 'transaction_reference', 'transferred_at',
            'submitted_amount', 'notes',
        ]);

        try {
            $result = $submitPayment->handle(
                $request->user(),
                $plan,
                $attributes,
                $request->file('payment_proof'),
            );
        } catch (BankTransferUnavailable) {
            return redirect()->route('billing.index')->with('toast', [
                'type' => 'warning',
                'title' => __('Payment unavailable'),
                'message' => __('This plan cannot currently be purchased by bank transfer.'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('billing.payment.create', $plan)
                ->withInput($request->safe()->except(['payment_proof', 'confirmation']))
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Payment submission failed'),
                    'message' => __('Your payment could not be submitted. Please try again.'),
                ]);
        }

        if (! $result->created) {
            return redirect()->route('billing.payments.show', $result->payment)->with('toast', [
                'type' => 'warning',
                'title' => __('Payment already pending'),
                'message' => __('An equivalent bank-transfer payment is already awaiting review.'),
            ]);
        }

        return redirect()->route('billing.payments.show', $result->payment)->with('toast', [
            'type' => 'success',
            'title' => __('Payment submitted'),
            'message' => __('Your bank transfer has been submitted for review. Your selected plan will be activated after the payment is approved.'),
        ]);
    }

    public function show(Request $request, Payment $subscriberPayment, CurrentSubscriptionResolver $subscriptions): View
    {
        Gate::authorize('view', $subscriberPayment);
        $subscriberPayment->load([
            'invoice:id,payment_id,number,status,issued_at,paid_at',
            'proof:id,payment_id,type,original_name,mime_type,size',
        ]);

        return view('billing.payments.show', [
            'payment' => $subscriberPayment,
            'subscriberPlanName' => $subscriptions->for($request->user())?->planName() ?? __('No active plan'),
        ]);
    }
}
