<?php

namespace App\Actions;

use App\DTOs\PaymentSubmissionResult;
use App\Enums\PaymentAttachmentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BankTransferPaymentSubmitted;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\BankTransferPlanEligibility;
use App\Services\DecimalMoney;
use App\Services\PaymentProofStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class SubmitBankTransferPayment
{
    public function __construct(
        private readonly BankTransferPlanEligibility $eligibility,
        private readonly PaymentProofStore $proofStore,
    ) {}

    /**
     * @param  array{payer_name: string, payer_bank_name: string, transaction_reference: string, transferred_at: string, submitted_amount: string, notes: ?string}  $attributes
     */
    public function handle(User $billingOwner, Plan $selectedPlan, array $attributes, UploadedFile $proof): PaymentSubmissionResult
    {
        $storedProof = null;

        try {
            $result = DB::transaction(function () use ($billingOwner, $selectedPlan, $attributes, $proof, &$storedProof): PaymentSubmissionResult {
                $lockedOwner = User::query()->lockForUpdate()->findOrFail($billingOwner->id);
                $plan = Plan::query()->lockForUpdate()->findOrFail($selectedPlan->id);

                $this->eligibility->assertEligible($lockedOwner, $plan);

                $existing = Payment::query()
                    ->ownedBy($lockedOwner)
                    ->where('plan_id', $plan->id)
                    ->where('payment_method', PaymentMethod::BANK_TRANSFER)
                    ->where('status', PaymentStatus::PENDING)
                    ->latest('id')
                    ->first();

                if ($existing) {
                    return new PaymentSubmissionResult($existing, false);
                }

                $storedProof = $this->proofStore->store($proof, $lockedOwner->id);
                $snapshot = $plan->subscriptionSnapshot();
                $now = now()->utc();

                $payment = new Payment;
                $payment->reference = 'PAY-'.Str::upper((string) Str::ulid());
                $payment->user_id = $lockedOwner->id;
                $payment->plan_id = $plan->id;
                $payment->payment_method = PaymentMethod::BANK_TRANSFER;
                $payment->status = PaymentStatus::PENDING;
                $payment->expected_amount_minor = $plan->price_minor;
                $payment->submitted_amount_minor = DecimalMoney::toMinor($attributes['submitted_amount']);
                $payment->currency = $plan->currency;
                $payment->plan_name_snapshot = $plan->name;
                $payment->plan_interval_snapshot = $plan->interval;
                $payment->plan_snapshot = $snapshot;
                $payment->payer_name = $attributes['payer_name'];
                $payment->payer_bank_name = $attributes['payer_bank_name'];
                $payment->transaction_reference = $attributes['transaction_reference'];
                $payment->transferred_at = CarbonImmutable::parse($attributes['transferred_at'], config('app.timezone'))->utc();
                $payment->notes = $attributes['notes'];
                $payment->submitted_at = $now;
                $payment->metadata = ['source' => 'subscriber_bank_transfer'];
                $payment->save();

                $payment->attachments()->create([
                    'type' => PaymentAttachmentType::PAYMENT_PROOF,
                    ...$storedProof,
                ]);

                $invoice = new Invoice;
                $invoice->number = 'INV-'.Str::upper((string) Str::ulid());
                $invoice->user_id = $lockedOwner->id;
                $invoice->payment_id = $payment->id;
                $invoice->plan_id = $plan->id;
                $invoice->status = PaymentStatus::PENDING;
                $invoice->subtotal_minor = $plan->price_minor;
                $invoice->total_minor = $plan->price_minor;
                $invoice->currency = $plan->currency;
                $invoice->description = $plan->name.' '.__('subscription');
                $invoice->issued_at = $now;
                $invoice->metadata = ['plan_snapshot' => $snapshot];
                $invoice->save();

                return new PaymentSubmissionResult($payment, true);
            });
        } catch (Throwable $exception) {
            $this->proofStore->cleanup($storedProof);

            throw $exception;
        }

        if ($result->created) {
            try {
                BankTransferPaymentSubmitted::dispatch($result->payment);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $result;
    }
}
