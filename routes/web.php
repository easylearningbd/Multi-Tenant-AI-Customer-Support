<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\SupportTicketAttachmentController as AdminSupportTicketAttachmentController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\SupportTicketReplyController as AdminSupportTicketReplyController;
use App\Http\Controllers\Admin\SupportTicketStatusController as AdminSupportTicketStatusController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BankTransferPaymentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriberDashboardController;
use App\Http\Controllers\SupportTicketAttachmentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SupportTicketReplyController;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::bind('subscriber', fn (string $value): User => User::query()
    ->subscribers()
    ->whereKey($value)
    ->firstOrFail());

Route::bind('subscriberTicket', function (string $value): SupportTicket {
    $actor = request()->user();

    if (! $actor instanceof User || $actor->role !== UserRole::USER) {
        $ticket = new SupportTicket;
        $ticket->reference = $value;

        return $ticket;
    }

    return SupportTicket::query()
        ->ownedBy($actor)
        ->notArchived()
        ->where('reference', $value)
        ->firstOrFail();
});

Route::bind('subscriberPayment', function (string $value): Payment {
    $actor = request()->user();

    if (! $actor instanceof User || $actor->role !== UserRole::USER) {
        $payment = new Payment;
        $payment->reference = $value;

        return $payment;
    }

    return Payment::query()
        ->ownedBy($actor)
        ->where('reference', $value)
        ->firstOrFail();
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', SubscriberDashboardController::class)
    ->middleware(['auth', 'role:user'])
    ->name('dashboard');

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/billing', BillingController::class)->name('billing.index');
    Route::get('/billing/pay/{plan}', [BankTransferPaymentController::class, 'create'])
        ->whereNumber('plan')
        ->name('billing.payment.create');
    Route::post('/billing/pay/{plan}/bank-transfer', [BankTransferPaymentController::class, 'store'])
        ->whereNumber('plan')
        ->middleware('throttle:bank-transfer-payment')
        ->name('billing.bank-transfer.store');
    Route::get('/billing/payments/{subscriberPayment}', [BankTransferPaymentController::class, 'show'])
        ->name('billing.payments.show');
    Route::get('/billing/payments/{subscriberPayment}/proof', [PaymentProofController::class, 'download'])
        ->name('billing.payments.proof.download');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('settings/support')->name('support-tickets.')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('create', [SupportTicketController::class, 'create'])->name('create');
        Route::post('/', [SupportTicketController::class, 'store'])
            ->middleware('throttle:support-ticket-create')
            ->name('store');
        Route::get('{subscriberTicket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('{subscriberTicket}/replies', [SupportTicketReplyController::class, 'store'])
            ->middleware('throttle:support-ticket-reply')
            ->name('replies.store');
        Route::get('{subscriberTicket}/attachments/{attachment}', [SupportTicketAttachmentController::class, 'download'])
            ->whereNumber('attachment')
            ->name('attachments.download');
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthenticatedSessionController::class, 'create'])
            ->name('login');
        Route::post('login', [AdminAuthenticatedSessionController::class, 'store'])
            ->name('login.store');
    });

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::delete('profile/avatar', [AdminProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/{subscriber}', [AdminUserController::class, 'show'])->whereNumber('subscriber')->name('users.show');
        Route::get('users/{subscriber}/edit', [AdminUserController::class, 'edit'])->whereNumber('subscriber')->name('users.edit');
        Route::put('users/{subscriber}', [AdminUserController::class, 'update'])->whereNumber('subscriber')->name('users.update');
        Route::delete('users/{subscriber}', [AdminUserController::class, 'destroy'])->whereNumber('subscriber')->name('users.destroy');

        Route::patch('plans/{plan}/status', [AdminPlanController::class, 'updateStatus'])->name('plans.status.update');
        Route::resource('plans', AdminPlanController::class);

        Route::get('support-tickets', [AdminSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('support-tickets/{adminTicket}', [AdminSupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('support-tickets/{adminTicket}/replies', [AdminSupportTicketReplyController::class, 'store'])
            ->middleware('throttle:support-ticket-reply')
            ->name('support-tickets.replies.store');
        Route::patch('support-tickets/{adminTicket}/status', AdminSupportTicketStatusController::class)->name('support-tickets.status.update');
        Route::delete('support-tickets/{adminTicket}', [AdminSupportTicketController::class, 'archive'])->name('support-tickets.archive');
        Route::get('support-tickets/{adminTicket}/attachments/{attachment}', AdminSupportTicketAttachmentController::class)
            ->whereNumber('attachment')
            ->name('support-tickets.attachments.download');

        Route::post('logout', [AdminAuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});

require __DIR__.'/auth.php';
