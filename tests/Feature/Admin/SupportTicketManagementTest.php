<?php

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketSenderType;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportTicketAdminReplied;
use App\Notifications\SupportTicketStatusChangedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function adminTicketFor(?User $subscriber, array $attributes = []): SupportTicket
{
    return SupportTicket::factory()->create([
        'requester_id' => $subscriber?->id,
        ...$attributes,
    ]);
}

function adminMessageFor(SupportTicket $ticket, ?User $sender, array $attributes = []): SupportTicketMessage
{
    return SupportTicketMessage::factory()->create([
        'support_ticket_id' => $ticket->id,
        'sender_id' => $sender?->id,
        ...$attributes,
    ]);
}

it('protects every admin support route from guests and subscribers', function () {
    $subscriber = User::factory()->subscriber()->create();
    $ticket = adminTicketFor($subscriber);
    $message = adminMessageFor($ticket, $subscriber);
    $attachment = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $message->id,
        'disk' => 'local',
        'path' => "support-tickets/{$ticket->id}/{$message->id}/file.txt",
        'original_name' => 'file.txt',
        'mime_type' => 'text/plain',
        'size' => 4,
    ]);

    $guestRequests = [
        fn () => $this->get(route('admin.support-tickets.index')),
        fn () => $this->get(route('admin.support-tickets.show', $ticket)),
        fn () => $this->post(route('admin.support-tickets.replies.store', $ticket)),
        fn () => $this->patch(route('admin.support-tickets.status.update', $ticket)),
        fn () => $this->delete(route('admin.support-tickets.archive', $ticket)),
        fn () => $this->get(route('admin.support-tickets.attachments.download', [$ticket, $attachment])),
    ];

    foreach ($guestRequests as $request) {
        $request()->assertRedirect(route('admin.login'));
    }

    $subscriberRequests = [
        fn () => $this->actingAs($subscriber)->get(route('admin.support-tickets.index')),
        fn () => $this->actingAs($subscriber)->get(route('admin.support-tickets.show', $ticket)),
        fn () => $this->actingAs($subscriber)->post(route('admin.support-tickets.replies.store', $ticket)),
        fn () => $this->actingAs($subscriber)->patch(route('admin.support-tickets.status.update', $ticket)),
        fn () => $this->actingAs($subscriber)->delete(route('admin.support-tickets.archive', $ticket)),
        fn () => $this->actingAs($subscriber)->get(route('admin.support-tickets.attachments.download', [$ticket, $attachment])),
    ];

    foreach ($subscriberRequests as $request) {
        $request()->assertForbidden();
    }
});

it('lists all active subscriber tickets with safe search filters sorting and pagination', function () {
    $admin = User::factory()->admin()->create();
    $firstSubscriber = User::factory()->subscriber()->create(['name' => 'Unique Requester']);
    $secondSubscriber = User::factory()->subscriber()->create();
    $older = adminTicketFor($firstSubscriber, [
        'subject' => 'Unique billing incident',
        'category' => 'Billing',
        'priority' => SupportTicketPriority::URGENT,
        'status' => SupportTicketStatus::AWAITING_USER,
        'last_activity_at' => now()->subDay(),
    ]);
    $newer = adminTicketFor($secondSubscriber, ['last_activity_at' => now()]);
    $archived = adminTicketFor($secondSubscriber, ['subject' => 'Archived private request', 'archived_at' => now()]);
    SupportTicket::factory()->count(14)->create(['last_activity_at' => now()->subDays(2)]);

    $page = $this->actingAs($admin)->get(route('admin.support-tickets.index'))
        ->assertOk()
        ->assertSee('Support Tickets')
        ->assertSee($newer->reference)
        ->assertSee($older->reference)
        ->assertDontSee($archived->subject)
        ->viewData('tickets');

    expect($page->perPage())->toBe(15)
        ->and($page->total())->toBe(16)
        ->and($page->items()[0]->id)->toBe($newer->id)
        ->and($page->nextPageUrl())->toContain('page=2');

    foreach ([$older->reference, 'Unique billing', 'Unique Requester', 'Billing'] as $search) {
        $this->actingAs($admin)->get(route('admin.support-tickets.index', ['search' => $search]))
            ->assertOk()->assertSee($older->reference);
    }

    $filtered = $this->actingAs($admin)->get(route('admin.support-tickets.index', [
        'priority' => 'urgent',
        'status' => 'awaiting_user',
        'per_page' => 10,
        'sort' => 'subject',
        'direction' => 'asc',
    ]))->assertOk()->viewData('tickets');
    expect($filtered->total())->toBe(1)
        ->and($filtered->first()->id)->toBe($older->id);

    $persistentPage = $this->actingAs($admin)->get(route('admin.support-tickets.index', [
        'per_page' => 10,
        'sort' => 'subject',
        'direction' => 'asc',
    ]))->assertOk()->viewData('tickets');
    expect($persistentPage->nextPageUrl())->toContain('per_page=10')
        ->and($persistentPage->nextPageUrl())->toContain('sort=subject')
        ->and($persistentPage->nextPageUrl())->toContain('direction=asc');

    $invalid = $this->actingAs($admin)->get(route('admin.support-tickets.index', [
        'sort' => 'requester_id desc; drop table users',
        'direction' => 'sideways',
        'per_page' => 999,
    ]))->assertOk();
    expect($invalid->viewData('filters')['sort'])->toBe('last_activity_at')
        ->and($invalid->viewData('tickets')->perPage())->toBe(15);

    $archivedPage = $this->actingAs($admin)->get(route('admin.support-tickets.index', ['visibility' => 'archived']))
        ->assertOk()->assertSee($archived->subject)->assertDontSee($older->subject)->viewData('tickets');
    expect($archivedPage->total())->toBe(1);
});

it('renders only the selected ticket conversation safely with verified staff markers and missing requester fallback', function () {
    $admin = User::factory()->admin()->create(['name' => 'Support Operator']);
    $subscriber = User::factory()->subscriber()->create(['name' => 'Ticket Owner']);
    $ticket = adminTicketFor($subscriber, ['subject' => '<script>alert(1)</script> Account issue']);
    adminMessageFor($ticket, $subscriber, ['body' => '<img src=x onerror=alert(1)> Subscriber message']);
    adminMessageFor($ticket, $admin, ['sender_type' => SupportTicketSenderType::STAFF, 'body' => 'Verified staff reply']);
    adminMessageFor($ticket, $subscriber, ['sender_type' => SupportTicketSenderType::STAFF, 'body' => 'Forged staff role']);
    $other = adminTicketFor($subscriber);
    adminMessageFor($other, $subscriber, ['body' => 'Unrelated private conversation']);

    $content = $this->actingAs($admin)->get(route('admin.support-tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Ticket Owner')
        ->assertSee('Subscriber message')
        ->assertSee('Verified staff reply')
        ->assertSee('Forged staff role')
        ->assertDontSee('Unrelated private conversation')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x', false)
        ->getContent();

    expect(substr_count($content, '>Staff<'))->toBe(1);

    $missingRequester = adminTicketFor(null, ['subject' => 'Former account ticket']);
    $this->actingAs($admin)->get(route('admin.support-tickets.show', $missingRequester))
        ->assertOk()->assertSee('Former subscriber')->assertSee('Not available');
});

it('stores an idempotent admin reply from trusted server values and notifies only the requester', function () {
    Notification::fake();
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $requester = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();
    $ticket = adminTicketFor($requester, ['status' => SupportTicketStatus::AWAITING_SUPPORT, 'last_activity_at' => now()->subDay()]);
    $token = (string) Str::uuid();

    $payload = [
        'message' => 'The requested diagnostic is attached.',
        'submission_token' => $token,
        'sender_id' => $requester->id,
        'sender_type' => SupportTicketSenderType::SUBSCRIBER->value,
        'requester_id' => $otherSubscriber->id,
        'status' => SupportTicketStatus::CLOSED->value,
        'attachments' => [UploadedFile::fake()->create('diagnostic.txt', 2, 'text/plain')],
    ];

    $this->actingAs($admin)->post(route('admin.support-tickets.replies.store', $ticket), $payload)
        ->assertRedirect(route('admin.support-tickets.show', $ticket))
        ->assertSessionHas('toast.message', 'Reply sent successfully.');
    $this->actingAs($admin)->post(route('admin.support-tickets.replies.store', $ticket), $payload)
        ->assertSessionHas('toast.message', 'Reply sent successfully.');

    $ticket->refresh();
    $message = $ticket->messages()->sole();
    expect($message->sender_id)->toBe($admin->id)
        ->and($message->sender_type)->toBe(SupportTicketSenderType::STAFF)
        ->and($message->submission_token)->toBe($token)
        ->and($ticket->requester_id)->toBe($requester->id)
        ->and($ticket->status)->toBe(SupportTicketStatus::AWAITING_USER)
        ->and($ticket->last_activity_at->greaterThan(now()->subMinute()))->toBeTrue()
        ->and($message->attachments()->count())->toBe(1);

    $attachment = $message->attachments()->sole();
    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->path)->toStartWith("support-tickets/{$ticket->id}/{$message->id}/")
        ->and($attachment->path)->not->toContain('diagnostic.txt');

    Notification::assertSentTo($requester, SupportTicketAdminReplied::class, function ($notification) use ($requester): bool {
        $data = $notification->toArray($requester);

        return $data['reply_preview'] === 'The requested diagnostic is attached.'
            && ! array_key_exists('path', $data);
    });
    Notification::assertNotSentTo($otherSubscriber, SupportTicketAdminReplied::class);
});

it('rejects replies to resolved and closed tickets', function (SupportTicketStatus $status) {
    $admin = User::factory()->admin()->create();
    $ticket = adminTicketFor(User::factory()->subscriber()->create(), ['status' => $status]);

    $this->actingAs($admin)->post(route('admin.support-tickets.replies.store', $ticket), [
        'message' => 'This should remain blocked.',
        'submission_token' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['message'], null, 'adminSupportReply');

    $this->assertDatabaseCount('support_ticket_messages', 0);
})->with([SupportTicketStatus::RESOLVED, SupportTicketStatus::CLOSED]);

it('validates admin reply attachments and cleans stored files after a failed transaction', function () {
    Storage::fake('local');
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = adminTicketFor(User::factory()->subscriber()->create());
    config()->set('support-tickets.attachments.max_kilobytes', 10);

    $invalidSets = [
        [UploadedFile::fake()->create('payload.exe', 2, 'application/x-msdownload')],
        [UploadedFile::fake()->image('large.jpg')->size(11)],
        array_map(fn (int $index) => UploadedFile::fake()->create("file-{$index}.txt", 1, 'text/plain'), range(1, 6)),
    ];

    foreach ($invalidSets as $files) {
        $this->actingAs($admin)->post(route('admin.support-tickets.replies.store', $ticket), [
            'message' => 'Please validate this attachment payload.',
            'submission_token' => (string) Str::uuid(),
            'attachments' => $files,
        ])->assertSessionHasErrors();
    }

    SupportTicketAttachment::creating(fn () => throw new RuntimeException('Simulated persistence failure'));
    try {
        $this->actingAs($admin)->post(route('admin.support-tickets.replies.store', $ticket), [
            'message' => 'This transaction should roll back.',
            'submission_token' => (string) Str::uuid(),
            'attachments' => [UploadedFile::fake()->create('safe.txt', 1, 'text/plain')],
        ])->assertSessionHas('toast.message', 'The reply could not be sent. Please try again.');
    } finally {
        SupportTicketAttachment::flushEventListeners();
    }

    $this->assertDatabaseCount('support_ticket_messages', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Notification::assertNothingSent();
});

it('downloads only managed attachments that belong to the selected ticket', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $ticket = adminTicketFor($subscriber);
    $message = adminMessageFor($ticket, $subscriber);
    $attachment = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $message->id,
        'disk' => 'local',
        'path' => "support-tickets/{$ticket->id}/{$message->id}/safe.txt",
        'original_name' => 'safe.txt',
        'mime_type' => 'text/plain',
        'size' => 4,
    ]);
    Storage::disk('local')->put($attachment->path, 'safe');

    $this->actingAs($admin)->get(route('admin.support-tickets.attachments.download', [$ticket, $attachment]))
        ->assertOk()->assertDownload('safe.txt')->assertHeader('X-Content-Type-Options', 'nosniff');

    $otherTicket = adminTicketFor($subscriber);
    $this->actingAs($admin)->get(route('admin.support-tickets.attachments.download', [$otherTicket, $attachment]))
        ->assertNotFound();

    Storage::disk('local')->delete($attachment->path);
    $this->actingAs($admin)->get(route('admin.support-tickets.attachments.download', [$ticket, $attachment]))
        ->assertNotFound();
});

it('updates valid statuses and timestamps without accepting ticket ownership fields', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $requester = User::factory()->subscriber()->create();
    $attacker = User::factory()->subscriber()->create();
    $ticket = adminTicketFor($requester, ['subject' => 'Original subject', 'status' => SupportTicketStatus::AWAITING_SUPPORT]);

    $this->actingAs($admin)->patch(route('admin.support-tickets.status.update', $ticket), [
        'status' => SupportTicketStatus::RESOLVED->value,
        'requester_id' => $attacker->id,
        'subject' => 'Injected subject',
    ])->assertSessionHas('toast.message', 'Ticket status updated successfully.');

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicketStatus::RESOLVED)
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->requester_id)->toBe($requester->id)
        ->and($ticket->subject)->toBe('Original subject');
    Notification::assertSentTo($requester, SupportTicketStatusChangedNotification::class);

    Notification::fake();
    $this->actingAs($admin)->patch(route('admin.support-tickets.status.update', $ticket), [
        'status' => SupportTicketStatus::RESOLVED->value,
    ])->assertSessionHas('toast.message', 'The ticket already has that status.');
    Notification::assertNothingSent();

    $this->actingAs($admin)->patch(route('admin.support-tickets.status.update', $ticket), [
        'status' => SupportTicketStatus::AWAITING_SUPPORT->value,
    ])->assertSessionHasNoErrors();
    $this->actingAs($admin)->patch(route('admin.support-tickets.status.update', $ticket), [
        'status' => SupportTicketStatus::CLOSED->value,
    ])->assertSessionHasNoErrors();
    expect($ticket->refresh()->closed_at)->not->toBeNull();
});

it('rejects invalid status values and disallowed transitions', function () {
    $admin = User::factory()->admin()->create();
    $ticket = adminTicketFor(User::factory()->subscriber()->create(), ['status' => SupportTicketStatus::CLOSED]);

    $this->actingAs($admin)->patch(route('admin.support-tickets.status.update', $ticket), ['status' => 'deleted'])
        ->assertSessionHasErrors(['status'], null, 'adminSupportStatus');
    $this->actingAs($admin)->patch(route('admin.support-tickets.status.update', $ticket), ['status' => SupportTicketStatus::AWAITING_USER->value])
        ->assertSessionHasErrors(['status'], null, 'adminSupportStatus');

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::CLOSED);
});

it('archives without deleting support history and hides the ticket from subscriber endpoints', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $ticket = adminTicketFor($subscriber);
    $message = adminMessageFor($ticket, $subscriber);
    $attachment = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $message->id,
        'disk' => 'local',
        'path' => "support-tickets/{$ticket->id}/{$message->id}/history.txt",
        'original_name' => 'history.txt',
        'mime_type' => 'text/plain',
        'size' => 7,
    ]);

    $this->actingAs($admin)->delete(route('admin.support-tickets.archive', $ticket))
        ->assertRedirect(route('admin.support-tickets.index'))
        ->assertSessionHas('toast.message', 'Ticket archived successfully.');

    expect($ticket->refresh()->archived_at)->not->toBeNull();
    $this->assertDatabaseHas('support_ticket_messages', ['id' => $message->id]);
    $this->assertDatabaseHas('support_ticket_attachments', ['id' => $attachment->id]);

    $this->actingAs($admin)->get(route('admin.support-tickets.index'))->assertDontSee($ticket->subject);
    $this->actingAs($admin)->get(route('admin.support-tickets.index', ['visibility' => 'archived']))->assertSee($ticket->subject);
    $this->actingAs($subscriber)->get('/settings/support/'.$ticket->reference)->assertNotFound();
    $this->actingAs($subscriber)->post('/settings/support/'.$ticket->reference.'/replies', ['message' => 'Hidden reply'])->assertNotFound();
});

it('does not expose admin ticket creation or get mutation routes', function () {
    $admin = User::factory()->admin()->create();
    $ticket = adminTicketFor(User::factory()->subscriber()->create());

    $this->actingAs($admin)->get('/admin/support-tickets/create')->assertNotFound();
    $this->actingAs($admin)->get('/admin/support-tickets/'.$ticket->reference.'/replies')->assertMethodNotAllowed();
    $this->actingAs($admin)->get('/admin/support-tickets/'.$ticket->reference.'/status')->assertMethodNotAllowed();
});
