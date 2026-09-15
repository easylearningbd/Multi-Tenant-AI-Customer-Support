<?php

use App\Actions\ApproveBankTransferPayment;
use App\Actions\RejectBankTransferPayment;
use App\Enums\PaymentAttachmentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttachment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BankTransferPaymentStatusNotification;
use App\Notifications\BankTransferPaymentSubmittedNotification;
use App\Services\PlanLimitService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('billing.bank_transfer', array_replace_recursive(
        config('billing.bank_transfer'),
        [
            'enabled' => true,
            'bank_name' => 'NeuralDesk Test Bank',
            'account_name' => 'NeuralDesk Inc.',
            'account_number' => '000123456789',
            'branch_name' => 'Main Branch',
            'routing_number' => '110000',
            'swift_code' => 'NDSKUS01',
            'iban' => null,
            'currency' => 'USD',
            'instructions' => 'Include your plan name in the transfer memo.',
            'future_transfer_tolerance_minutes' => 15,
            'submission_rate_per_minute' => 20,
            'proofs' => [
                'disk' => 'local',
                'directory' => 'payment-proofs',
                'max_kilobytes' => 10240,
            ],
        ],
    ));
});

function bankPaymentPayload(array $overrides = []): array
{
    return array_replace([
        'payer_name' => 'Subscriber Person',
        'payer_bank_name' => 'Customer Bank',
        'transaction_reference' => 'WIRE-ABC-123',
        'transferred_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'submitted_amount' => '29.00',
        'notes' => 'Transfer completed from business account.',
        'confirmation' => '1',
        'payment_proof' => UploadedFile::fake()->image('receipt.jpg', 600, 400),
    ], $overrides);
}

function makePendingBankPayment(User $subscriber, Plan $plan, array $overrides = []): Payment
{
    $payment = Payment::factory()->for($subscriber)->for($plan)->create(array_replace([
        'plan_name_snapshot' => $plan->name,
        'plan_interval_snapshot' => $plan->interval,
        'plan_snapshot' => $plan->subscriptionSnapshot(),
        'expected_amount_minor' => $plan->price_minor,
        'submitted_amount_minor' => $plan->price_minor,
        'currency' => $plan->currency,
    ], $overrides));

    Invoice::factory()->for($subscriber)->for($plan)->for($payment)->create([
        'status' => $payment->status,
        'subtotal_minor' => $payment->expected_amount_minor,
        'total_minor' => $payment->expected_amount_minor,
        'currency' => $payment->currency,
        'description' => $payment->plan_name_snapshot.' subscription',
    ]);

    $payment->attachments()->create([
        'type' => PaymentAttachmentType::PAYMENT_PROOF,
        'disk' => 'local',
        'path' => 'payment-proofs/'.$subscriber->id.'/'.$payment->reference.'.pdf',
        'original_name' => 'bank-receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 5,
    ]);

    return $payment;
}

function makeSubscriberSubscription(User $subscriber, Plan $plan, array $overrides = []): Subscription
{
    return Subscription::factory()->for($subscriber)->for($plan)->create(array_replace([
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
        'provider' => 'test',
        'provider_subscription_id' => fake()->uuid(),
        'plan_snapshot' => $plan->subscriptionSnapshot(),
    ], $overrides));
}

test('payment routes enforce subscriber authentication and role access', function () {
    $plan = Plan::factory()->create();
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $payment = makePendingBankPayment($subscriber, $plan);

    $this->get(route('billing.payment.create', $plan))->assertRedirect(route('login'));
    $this->post(route('billing.bank-transfer.store', $plan))->assertRedirect(route('login'));
    $this->get(route('billing.payments.show', $payment))->assertRedirect(route('login'));
    $this->get(route('billing.payments.proof.download', $payment))->assertRedirect(route('login'));

    $this->actingAs($admin)->get(route('billing.payment.create', $plan))->assertForbidden();
    $this->actingAs($admin)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload())->assertForbidden();
    $this->actingAs($admin)->get(route('billing.payments.show', $payment))->assertForbidden();
});

test('subscriber can open eligible payment page with immutable server plan data and disabled stripe', function () {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create([
        'name' => 'Secure Growth',
        'description' => 'Server-owned plan description.',
        'price_minor' => 4900,
        'currency' => 'USD',
        'features' => ['Private feature'],
    ]);

    $this->actingAs($subscriber)->get(route('billing.payment.create', $plan))
        ->assertOk()
        ->assertSee('Complete payment')
        ->assertSee('Secure Growth')
        ->assertSee('USD 49.00')
        ->assertSee('NeuralDesk Test Bank')
        ->assertSee('Bank Transfer')
        ->assertSee('Stripe')
        ->assertSee('Coming soon')
        ->assertSee('aria-disabled="true"', false)
        ->assertDontSee('stripe.js', false);

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee(route('billing.payment.create', $plan), false);
});

test('ineligible plans and incomplete bank configuration cannot enter checkout', function (string $state) {
    $subscriber = User::factory()->subscriber()->create();
    $plan = match ($state) {
        'inactive' => Plan::factory()->inactive()->create(),
        'trial' => Plan::factory()->trial()->create(),
        'custom' => Plan::factory()->create(['custom_pricing' => true]),
        'zero price' => Plan::factory()->create(['price_minor' => 0]),
        'currency' => Plan::factory()->create(['currency' => 'EUR']),
        default => Plan::factory()->create(),
    };

    if ($state === 'missing config') {
        config()->set('billing.bank_transfer.account_number', null);
    }

    $this->actingAs($subscriber)->get(route('billing.payment.create', $plan))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'warning');

    $this->actingAs($subscriber)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload())
        ->assertRedirect(route('billing.index'));
    $this->assertDatabaseCount('payments', 0);
})->with(['inactive', 'trial', 'custom', 'zero price', 'currency', 'missing config']);

test('an active current plan cannot be submitted as a duplicate purchase', function () {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();
    makeSubscriberSubscription($subscriber, $plan);

    $this->actingAs($subscriber)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload())
        ->assertRedirect(route('billing.index'));

    $this->assertDatabaseCount('payments', 0);
});

test('valid bank transfer creates pending payment invoice and private proof without activating plan', function () {
    Storage::fake('local');
    Notification::fake();
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $trial = Plan::factory()->trial()->create();
    $trialSubscription = makeSubscriberSubscription($subscriber, $trial, [
        'status' => SubscriptionStatus::TRIALING,
        'trial_ends_at' => now()->addWeek(),
        'current_period_ends_at' => now()->addWeek(),
    ]);
    $plan = Plan::factory()->create([
        'name' => 'Growth Monthly',
        'price_minor' => 4900,
        'currency' => 'USD',
        'features' => ['Priority answers'],
    ]);

    $response = $this->actingAs($subscriber)->post(
        route('billing.bank-transfer.store', $plan),
        bankPaymentPayload([
            'submitted_amount' => '48.25',
            'expected_amount' => '1.00',
            'currency' => 'EUR',
            'status' => PaymentStatus::PAID->value,
            'user_id' => User::factory()->subscriber()->create()->id,
        ]),
    );

    $payment = Payment::query()->sole();
    $response->assertRedirect(route('billing.payments.show', $payment));
    expect($payment->status)->toBe(PaymentStatus::PENDING)
        ->and($payment->payment_method)->toBe(PaymentMethod::BANK_TRANSFER)
        ->and($payment->user_id)->toBe($subscriber->id)
        ->and($payment->expected_amount_minor)->toBe(4900)
        ->and($payment->submitted_amount_minor)->toBe(4825)
        ->and($payment->currency)->toBe('USD')
        ->and($payment->plan_snapshot['features'])->toBe(['Priority answers'])
        ->and($payment->subscription_id)->toBeNull()
        ->and($payment->reference)->toStartWith('PAY-')
        ->and($payment->invoice->number)->toStartWith('INV-')
        ->and($payment->invoice->status)->toBe(PaymentStatus::PENDING)
        ->and($subscriber->subscriptions()->count())->toBe(1)
        ->and($trialSubscription->refresh()->status)->toBe(SubscriptionStatus::TRIALING);

    $proof = $payment->proof;
    expect($proof)->not->toBeNull()
        ->and($proof->type)->toBe(PaymentAttachmentType::PAYMENT_PROOF)
        ->and($proof->disk)->toBe('local')
        ->and($proof->path)->toStartWith('payment-proofs/'.$subscriber->id.'/')
        ->and($proof->path)->not->toContain('receipt.jpg');
    Storage::disk('local')->assertExists($proof->path);
    Notification::assertSentTo($admin, BankTransferPaymentSubmittedNotification::class);
});

test('equivalent repeated submissions return the existing pending payment without duplicate records', function () {
    Storage::fake('local');
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($subscriber)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload());
    $payment = Payment::query()->sole();

    $this->actingAs($subscriber)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload([
        'transaction_reference' => 'RETRY-SECOND',
    ]))->assertRedirect(route('billing.payments.show', $payment))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['title'] === 'Payment already pending');

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('payment_attachments', 1);
    expect(Storage::disk('local')->allFiles('payment-proofs'))->toHaveCount(1);
});

test('failed database persistence rolls back records and removes newly stored proof', function () {
    Storage::fake('local');
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();
    PaymentAttachment::creating(function (): void {
        throw new RuntimeException('Simulated database failure.');
    });

    try {
        $this->actingAs($subscriber)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload())
            ->assertRedirect(route('billing.payment.create', $plan))
            ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error');
    } finally {
        PaymentAttachment::flushEventListeners();
    }

    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('payment_attachments', 0);
    expect(Storage::disk('local')->allFiles('payment-proofs'))->toBeEmpty();
});

test('bank transfer validation rejects invalid fields and future dates', function (array $override, string $field) {
    Storage::fake('local');
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();

    $override = collect($override)->map(fn (mixed $value): mixed => $value instanceof Closure ? $value() : $value)->all();

    $this->actingAs($subscriber)
        ->from(route('billing.payment.create', $plan))
        ->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload($override))
        ->assertRedirect(route('billing.payment.create', $plan))
        ->assertSessionHasErrors([$field], null, 'bankTransfer');

    $this->assertDatabaseCount('payments', 0);
})->with([
    'payer required' => [['payer_name' => ''], 'payer_name'],
    'bank required' => [['payer_bank_name' => ''], 'payer_bank_name'],
    'reference required' => [['transaction_reference' => ''], 'transaction_reference'],
    'date invalid' => [['transferred_at' => 'not-a-date'], 'transferred_at'],
    'date too far future' => [['transferred_at' => fn () => now()->addHour()->format('Y-m-d H:i:s')], 'transferred_at'],
    'amount zero' => [['submitted_amount' => '0.00'], 'submitted_amount'],
    'amount negative' => [['submitted_amount' => '-1.00'], 'submitted_amount'],
    'amount too precise' => [['submitted_amount' => '29.999'], 'submitted_amount'],
    'confirmation required' => [['confirmation' => null], 'confirmation'],
    'proof required' => [['payment_proof' => null], 'payment_proof'],
]);

test('valid PDF and image proof formats are accepted', function (UploadedFile $proof) {
    Storage::fake('local');
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($subscriber)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload([
        'payment_proof' => $proof,
    ]))->assertRedirect();

    $this->assertDatabaseCount('payment_attachments', 1);
})->with([
    'pdf' => fn () => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
    'jpeg' => fn () => UploadedFile::fake()->image('receipt.jpeg'),
    'png' => fn () => UploadedFile::fake()->image('receipt.png'),
]);

test('unsafe oversized and mismatched proofs are rejected', function (UploadedFile $proof) {
    Storage::fake('local');
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($subscriber)->post(route('billing.bank-transfer.store', $plan), bankPaymentPayload([
        'payment_proof' => $proof,
    ]))->assertSessionHasErrors(['payment_proof'], null, 'bankTransfer');

    $this->assertDatabaseCount('payments', 0);
})->with([
    'oversized' => fn () => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
    'executable' => fn () => UploadedFile::fake()->create('malware.exe', 2, 'application/x-msdownload'),
    'html' => fn () => UploadedFile::fake()->createWithContent('receipt.html', '<html></html>'),
    'svg' => fn () => UploadedFile::fake()->createWithContent('receipt.svg', '<svg></svg>'),
    'archive' => fn () => UploadedFile::fake()->create('receipt.zip', 2, 'application/zip'),
    'double extension' => fn () => UploadedFile::fake()->image('payload.php.jpg'),
    'mismatch' => fn () => UploadedFile::fake()->create('receipt.pdf', 10, 'image/png'),
]);

test('payment details and private proof are owner scoped and missing objects return not found', function () {
    Storage::fake('local');
    $owner = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();
    $payment = makePendingBankPayment($owner, $plan, ['notes' => '<script>unsafe</script>']);
    $proof = $payment->proof;
    Storage::disk('local')->put($proof->path, 'proof');

    $this->actingAs($owner)->get(route('billing.payments.show', $payment))
        ->assertOk()
        ->assertSee($payment->reference)
        ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)
        ->assertDontSee('<script>unsafe</script>', false);
    $this->actingAs($owner)->get(route('billing.payments.proof.download', $payment))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($other)->get(route('billing.payments.show', $payment))->assertNotFound();
    $this->actingAs($other)->get(route('billing.payments.proof.download', $payment))->assertNotFound();

    $proof->update(['path' => '../outside.pdf']);
    $this->actingAs($owner)->get(route('billing.payments.proof.download', $payment))->assertNotFound();
    $proof->update(['path' => 'payment-proofs/'.$owner->id.'/missing.pdf']);
    $this->actingAs($owner)->get(route('billing.payments.proof.download', $payment))->assertNotFound();
});

test('billing history is owner scoped newest first and shows pending status', function () {
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();
    $older = makePendingBankPayment($subscriber, $plan, ['reference' => 'PAY-OLDER', 'submitted_at' => now()->subDay()]);
    $newer = makePendingBankPayment($subscriber, $plan, ['reference' => 'PAY-NEWER', 'submitted_at' => now()]);
    $hidden = makePendingBankPayment($other, $plan, ['reference' => 'PAY-OTHER-TENANT']);

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSeeInOrder([$newer->reference, $older->reference])
        ->assertSee('Pending')
        ->assertSee('Bank transfer')
        ->assertDontSee($hidden->reference);
});

test('billing history is paginated without loading every payment', function () {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();

    foreach (range(1, 11) as $index) {
        makePendingBankPayment($subscriber, $plan, [
            'reference' => 'PAY-PAGE-'.$index,
            'submitted_at' => now()->subMinutes($index),
        ]);
    }

    $response = $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('PAY-PAGE-1')
        ->assertDontSee('PAY-PAGE-11');

    expect($response->viewData('billing')['invoices']->perPage())->toBe(10)
        ->and($response->viewData('billing')['invoices']->total())->toBe(11);
});

test('billing empty history remains available and no stripe processing route exists', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('No invoices yet.');

    $this->actingAs($subscriber)->post('/billing/stripe', [])->assertNotFound();
    expect(Payment::query()->where('payment_method', 'stripe')->count())->toBe(0);
});

test('approval requires an admin and atomically pays invoice and activates selected plan once', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-09-14 10:00:00'));
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $trial = Plan::factory()->trial()->create();
    $trialSubscription = makeSubscriberSubscription($subscriber, $trial, [
        'status' => SubscriptionStatus::TRIALING,
        'trial_ends_at' => now()->addWeek(),
        'current_period_ends_at' => now()->addWeek(),
    ]);
    $paidPlan = Plan::factory()->create([
        'interval' => PlanInterval::MONTHLY,
        'limits' => array_replace(array_fill_keys(array_keys(Plan::LIMITS), 0), ['chatbots_limit' => 7]),
    ]);
    $payment = makePendingBankPayment($subscriber, $paidPlan);

    $approved = app(ApproveBankTransferPayment::class)->handle($payment, $admin);
    $firstPeriodEnd = $approved->subscription->current_period_ends_at;
    $again = app(ApproveBankTransferPayment::class)->handle($approved, $admin);

    expect($again->status)->toBe(PaymentStatus::PAID)
        ->and($again->paid_at)->not->toBeNull()
        ->and($again->reviewed_by)->toBe($admin->id)
        ->and($again->invoice->status)->toBe(PaymentStatus::PAID)
        ->and($again->subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($again->subscription->provider)->toBe('bank_transfer')
        ->and($again->subscription->current_period_ends_at->toDateTimeString())->toBe('2026-10-14 10:00:00')
        ->and($again->subscription->current_period_ends_at->equalTo($firstPeriodEnd))->toBeTrue()
        ->and($trialSubscription->refresh()->status)->toBe(SubscriptionStatus::CANCELED)
        ->and($subscriber->subscriptions()->count())->toBe(2)
        ->and(app(PlanLimitService::class)->allows($again->subscription, 'chatbots_limit', 6))->toBeTrue()
        ->and(app(PlanLimitService::class)->allows($again->subscription, 'chatbots_limit', 7))->toBeFalse();

    Notification::assertSentToTimes($subscriber, BankTransferPaymentStatusNotification::class, 1);
});

test('manual renewal extends from an existing future period without duplicate extension', function () {
    $this->travelTo(Carbon::parse('2026-01-31 12:00:00'));
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create(['interval' => PlanInterval::MONTHLY]);
    $existing = makeSubscriberSubscription($subscriber, $plan, [
        'provider' => 'bank_transfer',
        'current_period_ends_at' => Carbon::parse('2026-02-28 12:00:00'),
    ]);
    $payment = makePendingBankPayment($subscriber, $plan);

    $first = app(ApproveBankTransferPayment::class)->handle($payment, $admin);
    $end = $first->subscription->current_period_ends_at;
    $second = app(ApproveBankTransferPayment::class)->handle($first, $admin);

    expect($end->toDateTimeString())->toBe('2026-03-28 12:00:00')
        ->and($second->subscription->current_period_ends_at->equalTo($end))->toBeTrue()
        ->and($existing->refresh()->status)->toBe(SubscriptionStatus::CANCELED)
        ->and($subscriber->subscriptions()->count())->toBe(2);
});

test('non admins cannot approve and invalid plans stay pending', function () {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();
    $payment = makePendingBankPayment($subscriber, $plan);

    expect(fn () => app(ApproveBankTransferPayment::class)->handle($payment, $subscriber))
        ->toThrow(AuthorizationException::class);

    $plan->update(['is_active' => false]);
    expect(fn () => app(ApproveBankTransferPayment::class)->handle($payment, User::factory()->admin()->create()))
        ->toThrow(DomainException::class);
    expect($payment->refresh()->status)->toBe(PaymentStatus::PENDING)
        ->and($subscriber->subscriptions()->count())->toBe(0);
});

test('rejection is admin only idempotent and leaves the current subscription unchanged', function () {
    Notification::fake();
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $currentPlan = Plan::factory()->create();
    $current = makeSubscriberSubscription($subscriber, $currentPlan);
    $selectedPlan = Plan::factory()->create();
    $payment = makePendingBankPayment($subscriber, $selectedPlan);

    expect(fn () => app(RejectBankTransferPayment::class)->handle($payment, $subscriber, 'Not authorized'))
        ->toThrow(AuthorizationException::class);

    $rejected = app(RejectBankTransferPayment::class)->handle($payment, $admin, 'Receipt could not be verified.');
    $again = app(RejectBankTransferPayment::class)->handle($rejected, $admin, 'Different reason ignored');

    expect($again->status)->toBe(PaymentStatus::REJECTED)
        ->and($again->rejection_reason)->toBe('Receipt could not be verified.')
        ->and($again->reviewed_by)->toBe($admin->id)
        ->and($again->invoice->status)->toBe(PaymentStatus::REJECTED)
        ->and($again->subscription_id)->toBeNull()
        ->and($current->refresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($subscriber->subscriptions()->count())->toBe(1);

    Notification::assertSentToTimes($subscriber, BankTransferPaymentStatusNotification::class, 1);
});
