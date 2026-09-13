<?php

namespace App\Http\Controllers;

use App\Enums\PaymentAttachmentType;
use App\Models\Payment;
use App\Models\PaymentAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentProofController extends Controller
{
    public function download(Payment $subscriberPayment): StreamedResponse
    {
        Gate::authorize('downloadProof', $subscriberPayment);

        $proof = PaymentAttachment::query()
            ->where('payment_id', $subscriberPayment->id)
            ->where('type', PaymentAttachmentType::PAYMENT_PROOF)
            ->firstOrFail();

        abort_unless(
            $proof->hasManagedPath() && Storage::disk($proof->disk)->exists($proof->path),
            404,
        );

        return Storage::disk($proof->disk)->download(
            $proof->path,
            $proof->original_name,
            [
                'Content-Type' => $proof->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
