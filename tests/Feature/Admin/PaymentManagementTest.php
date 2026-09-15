<?php

use App\Enums\PaymentAttachmentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BankTransferPaymentStatusNotification;
use App\Services\CurrentSubscriptionResolver;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

function makeAdminPayment(User $subscriber, Plan $plan, array $attributes = [], bool $withProof = true): Payment
{
    $payment = Payment::factory()->for($subscriber)->for($plan)->create(array_replace([
        'payment_method' => PaymentMethod::BANK_TRANSFER,
        'status' => PaymentStatus::PENDING,
        'plan_name_snapshot' => $plan->name,
        'plan_interval_snapshot' => $plan->interval,
        'plan_snapshot' => $plan->subscriptionSnapshot(),
        'expected_amount_minor' => $plan->price_minor,
        'submitted_amount_minor' => $plan->price_minor,
        'currency' => $plan->currency,
        'submitted_at' => now(),
    ], $attributes));

    Invoice::factory()->for($subscriber)->for($plan)->for($payment)->create([
        'status' => $payment->status,
        'subtotal_minor' => $payment->expected_amount_minor,
        'total_minor' => $payment->expected_amount_minor,
        'currency' => $payment->currency,
        'description' => $payment->plan_name_snapshot.' subscription',
    ]);

    if ($withProof) {
        $payment->attachments()->create([
            'type' => PaymentAttachmentType::PAYMENT_PROOF,
            'disk' => 'local',
            'path' => 'payment-proofs/'.$subscriber->id.'/'.$payment->reference.'.pdf',
            'original_name' => 'transfer-proof.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5,
        ]);
    }

    return $payment;
}

test('admin payment routes require an administrator and expose no delete or refund workflow', function () {
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $payment = makeAdminPayment($subscriber, Plan::factory()->create());

    foreach ([
        route('admin.payments.index'),
        route('admin.payments.show', $payment),
        route('admin.payments.proof.download', $payment),
    ] as $url) {
        auth()->logout();
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs($subscriber)->get($url)->assertForbidden();
    }

    $this->actingAs($subscriber)->post(route('admin.payments.approve', $payment), ['confirmation' => 1])->assertForbidden();
    $this->actingAs($subscriber)->post(route('admin.payments.reject', $payment), ['confirmation' => 1, 'rejection_reason' => 'Invalid receipt'])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();

    expect(Route::has('admin.payments.destroy'))->toBeFalse()
        ->and(Route::has('admin.refunds.index'))->toBeFalse();
    $this->actingAs($admin)->delete('/admin/payments/'.$payment->reference)->assertStatus(405);
});

test('admin can search filter sort and paginate payments from multiple subscribers', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create(['name' => 'Growth Secure']);
    $alpha = User::factory()->subscriber()->create(['name' => 'Alpha Customer', 'email' => 'alpha@example.com']);
    $beta = User::factory()->subscriber()->create(['name' => 'Beta Customer']);
    $older = makeAdminPayment($alpha, $plan, ['reference' => 'PAY-ALPHA-001', 'transaction_reference' => 'WIRE-SEARCH-001', 'submitted_at' => now()->subDay()]);
    $newer = makeAdminPayment($beta, $plan, ['reference' => 'PAY-BETA-002', 'status' => PaymentStatus::REJECTED, 'submitted_at' => now()]);

    $this->actingAs($admin)->get(route('admin.payments.index'))
        ->assertOk()->assertSeeInOrder([$newer->reference, $older->reference])
        ->assertSee('Alpha Customer')->assertSee('Beta Customer');

    foreach (['PAY-ALPHA', 'WIRE-SEARCH-001', 'Alpha Customer', 'alpha@example.com', $older->invoice->number] as $search) {
        $this->actingAs($admin)->get(route('admin.payments.index', ['search' => $search]))
            ->assertOk()->assertSee($older->reference)->assertDontSee($newer->reference);
    }
    $this->actingAs($admin)->get(route('admin.payments.index', ['search' => 'Growth Secure']))
        ->assertOk()->assertSee($older->reference)->assertSee($newer->reference);

    $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'rejected']))
        ->assertOk()->assertSee($newer->reference)->assertDontSee($older->reference);
    $this->actingAs($admin)->get(route('admin.payments.index', ['payment_method' => 'bank_transfer', 'sort' => 'not-a-column']))
        ->assertOk()->assertSee($older->reference);

    foreach (range(1, 14) as $index) {
        makeAdminPayment($alpha, $plan, ['reference' => 'PAY-PAGE-'.$index, 'submitted_at' => now()->subMinutes($index)]);
    }

    $response = $this->actingAs($admin)->get(route('admin.payments.index', ['search' => 'PAY-', 'page' => 2]))->assertOk();
    expect($response->viewData('payments')->perPage())->toBe(15)
        ->and($response->viewData('payments')->total())->toBe(16);
    $response->assertSee('search=PAY-', false);
});

test('payment details use snapshots escape content and never render raw metadata or private paths', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create(['name' => 'Safe Subscriber']);
    $plan = Plan::factory()->create(['name' => 'Original Plan', 'features' => ['Saved feature']]);
    $payment = makeAdminPayment($subscriber, $plan, [
        'submitted_amount_minor' => $plan->price_minor - 100,
        'notes' => '<script>alert(1)</script>',
        'metadata' => ['secret_token' => 'SHOULD-NOT-RENDER'],
    ]);
    $payment->proof->update(['path' => 'payment-proofs/'.$subscriber->id.'/private-location.pdf']);
    $plan->update(['name' => 'Edited Later']);

    $this->actingAs($admin)->get(route('admin.payments.show', $payment))
        ->assertOk()->assertSee($payment->reference)->assertSee('Original Plan')->assertSee('Saved feature')
        ->assertSee('Amount mismatch')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('SHOULD-NOT-RENDER')
        ->assertDontSee('private-location.pdf');
});

test('only an admin can securely download a managed payment proof', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $payment = makeAdminPayment($subscriber, Plan::factory()->create());
    Storage::disk('local')->put($payment->proof->path, 'proof-data');

    $this->actingAs($admin)->get(route('admin.payments.proof.download', $payment))
        ->assertOk()->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff');
    $this->actingAs($subscriber)->get(route('admin.payments.proof.download', $payment))->assertForbidden();

    $payment->proof->update(['path' => '../private.pdf']);
    $this->actingAs($admin)->get(route('admin.payments.proof.download', $payment))->assertNotFound();
    $payment->proof->update(['path' => 'payment-proofs/'.$subscriber->id.'/missing.pdf']);
    $this->actingAs($admin)->get(route('admin.payments.proof.download', $payment))->assertNotFound();
});

test('admin approval pays the immutable invoice activates the plan and is idempotent', function () {
    Notification::fake();
    $this->travelTo('2026-01-31 12:00:00');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $trial = Plan::factory()->trial()->create();
    $trialSubscription = Subscription::factory()->for($subscriber)->for($trial)->create([
        'status' => SubscriptionStatus::TRIALING,
        'starts_at' => now()->subDay(),
        'trial_ends_at' => now()->addWeek(),
        'current_period_ends_at' => now()->addWeek(),
        'plan_snapshot' => $trial->subscriptionSnapshot(),
    ]);
    $paidPlan = Plan::factory()->create([
        'interval' => PlanInterval::MONTHLY,
        'limits' => array_replace(array_fill_keys(array_keys(Plan::LIMITS), 0), ['chatbots_limit' => 3]),
    ]);
    $payment = makeAdminPayment($subscriber, $paidPlan);

    $payload = ['confirmation' => 1, 'review_note' => 'Receipt reconciled.'];
    $this->actingAs($admin)->post(route('admin.payments.approve', $payment), $payload)
        ->assertRedirect(route('admin.payments.show', $payment))->assertSessionHas('toast');

    $payment->refresh();
    $subscription = $payment->subscription;
    expect($payment->status)->toBe(PaymentStatus::PAID)
        ->and($payment->reviewed_by)->toBe($admin->id)
        ->and($payment->reviewNote())->toBe('Receipt reconciled.')
        ->and($payment->invoice->status)->toBe(PaymentStatus::PAID)
        ->and($subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($subscription->current_period_ends_at->toDateTimeString())->toBe('2026-02-28 12:00:00')
        ->and($trialSubscription->refresh()->status)->toBe(SubscriptionStatus::CANCELED)
        ->and(app(CurrentSubscriptionResolver::class)->for($subscriber)->id)->toBe($subscription->id)
        ->and(app(PlanLimitService::class)->allows($subscription, 'chatbots_limit', 2))->toBeTrue()
        ->and(app(PlanLimitService::class)->allows($subscription, 'chatbots_limit', 3))->toBeFalse();

    $dashboardResponse = $this->actingAs($subscriber)->get(route('dashboard'))->assertOk();
    expect($dashboardResponse->viewData('dashboard')['planName'])->toBe($paidPlan->name)
        ->and($dashboardResponse->viewData('dashboard')['chatbots']['limit'])->toBe(3);
    $dashboardResponse->assertSee($paidPlan->name)->assertDontSee($trial->name);

    $billingResponse = $this->actingAs($subscriber)->get(route('billing.index'))->assertOk();
    expect($billingResponse->viewData('billing')['subscription']->id)->toBe($subscription->id)
        ->and($billingResponse->viewData('billing')['current']['name'])->toBe($paidPlan->name)
        ->and($billingResponse->viewData('billing')['current']['features'])->toBe($subscription->featureList());

    $this->actingAs($admin)->post(route('admin.payments.approve', $payment), $payload)->assertSessionHas('toast');
    expect($subscriber->subscriptions()->count())->toBe(2)
        ->and($payment->refresh()->subscription_id)->toBe($subscription->id);
    Notification::assertSentToTimes($subscriber, BankTransferPaymentStatusNotification::class, 1);
});

test('approval requires proof and explicit mismatch acknowledgement and ignores authoritative field injection', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $otherPlan = Plan::factory()->create();
    $plan = Plan::factory()->create(['price_minor' => 4900]);
    $missingProof = makeAdminPayment($subscriber, $plan, [], false);

    $this->actingAs($admin)->post(route('admin.payments.approve', $missingProof), ['confirmation' => 1])
        ->assertSessionHas('toast');
    expect($missingProof->refresh()->status)->toBe(PaymentStatus::PENDING);

    $mismatch = makeAdminPayment($subscriber, $plan, ['submitted_amount_minor' => 4800]);
    $this->actingAs($admin)->post(route('admin.payments.approve', $mismatch), [
        'confirmation' => 1,
        'plan_id' => $otherPlan->id,
        'currency' => 'EUR',
        'reviewed_by' => $subscriber->id,
    ])->assertSessionHas('toast');
    expect($mismatch->refresh()->status)->toBe(PaymentStatus::PENDING);

    $this->actingAs($admin)->post(route('admin.payments.approve', $mismatch), [
        'confirmation' => 1,
        'amount_mismatch_acknowledged' => 1,
        'plan_id' => $otherPlan->id,
    ])->assertSessionHas('toast');
    expect($mismatch->refresh()->status)->toBe(PaymentStatus::PAID)
        ->and($mismatch->plan_id)->toBe($plan->id)
        ->and($mismatch->reviewed_by)->toBe($admin->id)
        ->and($mismatch->expected_amount_minor)->toBe(4900)
        ->and($mismatch->submitted_amount_minor)->toBe(4800)
        ->and($mismatch->metadata['amount_mismatch_acknowledged'])->toBeTrue();
});

test('currency or invoice snapshot inconsistency blocks approval without changing financial records', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create(['currency' => 'USD']);
    $payment = makeAdminPayment($subscriber, $plan);
    $snapshot = $payment->plan_snapshot;
    $snapshot['currency'] = 'EUR';
    $payment->update(['plan_snapshot' => $snapshot]);

    $this->actingAs($admin)->post(route('admin.payments.approve', $payment), ['confirmation' => 1])
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'warning');

    expect($payment->refresh()->status)->toBe(PaymentStatus::PENDING)
        ->and($payment->invoice->status)->toBe(PaymentStatus::PENDING)
        ->and($subscriber->subscriptions()->count())->toBe(0);
});

test('yearly bank transfer approval uses calendar-safe period arithmetic', function () {
    $this->travelTo('2028-02-29 09:30:00');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create(['interval' => PlanInterval::YEARLY]);
    $payment = makeAdminPayment($subscriber, $plan);

    $this->actingAs($admin)->post(route('admin.payments.approve', $payment), ['confirmation' => 1])
        ->assertRedirect(route('admin.payments.show', $payment));

    expect($payment->refresh()->subscription->current_period_ends_at->toDateTimeString())
        ->toBe('2029-02-28 09:30:00');
});

test('admin rejection requires a reason preserves current access and remains idempotent', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $currentPlan = Plan::factory()->create();
    $current = Subscription::factory()->for($subscriber)->for($currentPlan)->create(['plan_snapshot' => $currentPlan->subscriptionSnapshot()]);
    $selectedPlan = Plan::factory()->create();
    $payment = makeAdminPayment($subscriber, $selectedPlan);

    $this->actingAs($admin)->post(route('admin.payments.reject', $payment), ['confirmation' => 1])
        ->assertSessionHasErrors(['rejection_reason'], null, 'paymentRejection');
    expect($payment->refresh()->status)->toBe(PaymentStatus::PENDING);

    $payload = ['confirmation' => 1, 'rejection_reason' => 'The receipt could not be verified.', 'review_note' => 'Checked bank statement.'];
    $this->actingAs($admin)->post(route('admin.payments.reject', $payment), $payload)->assertSessionHas('toast');
    $this->actingAs($admin)->post(route('admin.payments.reject', $payment), array_replace($payload, ['rejection_reason' => 'Changed']))->assertSessionHas('toast');

    expect($payment->refresh()->status)->toBe(PaymentStatus::REJECTED)
        ->and($payment->rejection_reason)->toBe('The receipt could not be verified.')
        ->and($payment->reviewNote())->toBe('Checked bank statement.')
        ->and($payment->invoice->status)->toBe(PaymentStatus::REJECTED)
        ->and($payment->subscription_id)->toBeNull()
        ->and($current->refresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($subscriber->subscriptions()->count())->toBe(1);
    Notification::assertSentToTimes($subscriber, BankTransferPaymentStatusNotification::class, 1);

    $this->actingAs($admin)->post(route('admin.payments.approve', $payment), ['confirmation' => 1])->assertSessionHas('toast');
    expect($payment->refresh()->status)->toBe(PaymentStatus::REJECTED);
});
