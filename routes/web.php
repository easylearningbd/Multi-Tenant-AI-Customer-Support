<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\SupportTicketAttachmentController as AdminSupportTicketAttachmentController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\SupportTicketReplyController as AdminSupportTicketReplyController;
use App\Http\Controllers\Admin\SupportTicketStatusController as AdminSupportTicketStatusController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BankTransferPaymentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BotEmbedController;
use App\Http\Controllers\BotSettingsController;
use App\Http\Controllers\BotTrainingController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicWidgetApiController;
use App\Http\Controllers\PublicWidgetAssetController;
use App\Http\Controllers\PublicWidgetPageController;
use App\Http\Controllers\RagConversationController;
use App\Http\Controllers\SubscriberDashboardController;
use App\Http\Controllers\SupportTicketAttachmentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SupportTicketReplyController;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\KnowledgeSource;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::bind('subscriber', fn (string $value): User => User::query()
    ->subscribers()
    ->whereKey($value)
    ->firstOrFail());

Route::get('/widgets/v1/{publicWidget}/loader.js', PublicWidgetAssetController::class)
    ->whereUlid('publicWidget')
    ->name('widgets.loader.show');
Route::get('/widgets/v1/{publicWidget}/frame', [PublicWidgetPageController::class, 'frame'])
    ->whereUlid('publicWidget')
    ->name('widgets.frame.show');
Route::get('/chat/{publicWidget}', [PublicWidgetPageController::class, 'hosted'])
    ->whereUlid('publicWidget')
    ->name('widgets.hosted.show');
Route::get('/widgets/demo/{publicWidget}', [PublicWidgetPageController::class, 'demo'])
    ->whereUlid('publicWidget')
    ->name('widgets.demo.show');

Route::prefix('api/widgets/v1/{publicWidget}')->whereUlid('publicWidget')->name('widgets.api.')->group(function () {
    Route::post('sessions', [PublicWidgetApiController::class, 'bootstrap'])
        ->middleware('throttle:widget-bootstrap')->name('sessions.store');
    Route::post('prechat', [PublicWidgetApiController::class, 'prechat'])
        ->middleware('throttle:widget-message')->name('prechat.store');
    Route::post('messages', [PublicWidgetApiController::class, 'message'])
        ->middleware('throttle:widget-message')->name('messages.store');
    Route::get('conversations/{conversationUuid}', [PublicWidgetApiController::class, 'conversation'])
        ->whereUuid('conversationUuid')->middleware('throttle:widget-poll')->name('conversations.show');
    Route::post('handoff', [PublicWidgetApiController::class, 'handoff'])
        ->middleware('throttle:widget-message')->name('handoff.store');
});

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

Route::bind('subscriberBot', function (string $value): Bot {
    $actor = request()->user();

    if (! $actor instanceof User || $actor->role !== UserRole::USER) {
        $bot = new Bot;
        $bot->public_id = $value;

        return $bot;
    }

    return Bot::query()
        ->ownedBy($actor)
        ->where('public_id', $value)
        ->firstOrFail();
});

Route::bind('subscriberSource', function (string $value): KnowledgeSource {
    $actor = request()->user();

    if (! $actor instanceof User || $actor->role !== UserRole::USER) {
        $source = new KnowledgeSource;
        $source->uuid = $value;

        return $source;
    }

    return KnowledgeSource::query()->ownedBy($actor)->where('uuid', $value)->firstOrFail();
});

Route::bind('subscriberConversation', function (string $value): Conversation {
    $actor = request()->user();

    if (! $actor instanceof User || $actor->role !== UserRole::USER) {
        $conversation = new Conversation;
        $conversation->uuid = $value;

        return $conversation;
    }

    return Conversation::query()->ownedBy($actor)->where('uuid', $value)->firstOrFail();
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', SubscriberDashboardController::class)
    ->middleware(['auth', 'role:user'])
    ->name('dashboard');

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/bots', [BotController::class, 'index'])->name('bots.index');
    Route::post('/bots', [BotController::class, 'store'])->name('bots.store');
    Route::get('/bots/{subscriberBot}/setup', [BotController::class, 'setup'])->name('bots.setup');
    Route::get('/bots/{subscriberBot}/settings', [BotSettingsController::class, 'edit'])->name('bots.settings.edit');
    Route::put('/bots/{subscriberBot}/settings', [BotSettingsController::class, 'update'])->name('bots.settings.update');
    Route::get('/bots/{subscriberBot}/embed', [BotEmbedController::class, 'edit'])->name('bots.embed.edit');
    Route::put('/bots/{subscriberBot}/embed', [BotEmbedController::class, 'update'])->name('bots.embed.update');
    Route::delete('/bots/{subscriberBot}', [BotSettingsController::class, 'destroy'])->name('bots.destroy');
    Route::post('/bots/{subscriberBot}/rag/messages', [RagConversationController::class, 'store'])
        ->middleware('throttle:rag-message')
        ->name('bots.rag.messages.store');
    Route::get('/bots/{subscriberBot}/rag/conversations/{subscriberConversation}', [RagConversationController::class, 'show'])
        ->name('bots.rag.conversations.show');
    Route::prefix('/bots/{subscriberBot}/training')->name('bots.training.')->middleware('throttle:knowledge-training')->group(function () {
        Route::get('/', [BotTrainingController::class, 'index'])->withoutMiddleware('throttle:knowledge-training')->name('index');
        Route::post('/text', [BotTrainingController::class, 'text'])->name('text.store');
        Route::post('/files', [BotTrainingController::class, 'files'])->name('files.store');
        Route::post('/website', [BotTrainingController::class, 'website'])->name('website.store');
        Route::post('/retrain', [BotTrainingController::class, 'retrainAll'])->name('retrain-all');
        Route::post('/sources/{subscriberSource}/retrain', [BotTrainingController::class, 'retrain'])->name('sources.retrain');
        Route::delete('/sources/{subscriberSource}', [BotTrainingController::class, 'destroy'])->name('sources.destroy');
    });

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

        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/proof', AdminPaymentProofController::class)->name('payments.proof.download');
        Route::post('payments/{payment}/approve', [AdminPaymentController::class, 'approve'])->name('payments.approve');
        Route::post('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');

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
