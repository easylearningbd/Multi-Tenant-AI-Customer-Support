<?php

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketSenderType;
use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketCreated;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\NewSupportTicketNotification;
use App\Notifications\SupportTicketSubscriberRepliedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

function ticketFor(User $subscriber, array $attributes = []): SupportTicket
{
    return SupportTicket::factory()->for($subscriber, 'requester')->create($attributes);
}

function messageFor(SupportTicket $ticket, User $sender, array $attributes = []): SupportTicketMessage
{
    return SupportTicketMessage::factory()->for($ticket, 'ticket')->for($sender, 'sender')->create($attributes);
}

it('redirects guests from every support ticket screen and action', function () {
    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber);
    $message = messageFor($ticket, $subscriber);
    $attachment = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $message->id,
        'disk' => 'local',
        'path' => "support-tickets/{$ticket->id}/{$message->id}/file.txt",
        'original_name' => 'file.txt',
        'mime_type' => 'text/plain',
        'size' => 4,
    ]);

    $this->get(route('support-tickets.index'))->assertRedirect(route('login'));
    $this->get(route('support-tickets.create'))->assertRedirect(route('login'));
    $this->post(route('support-tickets.store'))->assertRedirect(route('login'));
    $this->get(route('support-tickets.show', $ticket))->assertRedirect(route('login'));
    $this->post(route('support-tickets.replies.store', $ticket))->assertRedirect(route('login'));
    $this->get(route('support-tickets.attachments.download', [$ticket, $attachment]))
        ->assertRedirect(route('login'));
});

it('forbids administrators from every subscriber support route', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber);
    $message = messageFor($ticket, $subscriber);
    $attachment = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $message->id,
        'disk' => 'local',
        'path' => "support-tickets/{$ticket->id}/{$message->id}/file.txt",
        'original_name' => 'file.txt',
        'mime_type' => 'text/plain',
        'size' => 4,
    ]);

    $this->actingAs($admin)->get(route('support-tickets.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('support-tickets.create'))->assertForbidden();
    $this->actingAs($admin)->post(route('support-tickets.store'))->assertForbidden();
    $this->actingAs($admin)->get(route('support-tickets.show', $ticket))->assertForbidden();
    $this->actingAs($admin)->post(route('support-tickets.replies.store', $ticket))->assertForbidden();
    $this->actingAs($admin)->get(route('support-tickets.attachments.download', [$ticket, $attachment]))
        ->assertForbidden();
});

it('lists only the authenticated subscribers tickets and renders an empty state', function () {
    $subscriber = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();
    $ownTicket = ticketFor($subscriber, ['subject' => 'My private support request']);
    $otherTicket = ticketFor($otherSubscriber, ['subject' => 'Another customer secret']);

    $this->actingAs($subscriber)->get(route('support-tickets.index'))
        ->assertOk()
        ->assertSee($ownTicket->subject)
        ->assertDontSee($otherTicket->subject);

    $ownTicket->delete();

    $this->actingAs($subscriber)->get(route('support-tickets.index'))
        ->assertOk()
        ->assertSee('No support tickets yet.');
});

it('creates a ticket and initial message from trusted server values', function () {
    Notification::fake();

    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($subscriber)->post(route('support-tickets.store'), [
        'subject' => '  Cannot connect my chatbot  ',
        'priority' => SupportTicketPriority::HIGH->value,
        'category' => '  Bot setup  ',
        'message' => 'Please help me connect the chatbot.',
        'requester_id' => $admin->id,
        'status' => SupportTicketStatus::CLOSED->value,
        'sender_id' => $admin->id,
        'sender_type' => SupportTicketSenderType::STAFF->value,
    ]);

    $ticket = SupportTicket::query()->sole();
    $message = $ticket->messages()->sole();

    $response->assertRedirect(route('support-tickets.show', $ticket))
        ->assertSessionHas('toast.message', 'Ticket created successfully.');
    expect($ticket->reference)->toBe('TKT-100001')
        ->and($ticket->requester_id)->toBe($subscriber->id)
        ->and($ticket->subject)->toBe('Cannot connect my chatbot')
        ->and($ticket->category)->toBe('Bot setup')
        ->and($ticket->status)->toBe(SupportTicketStatus::AWAITING_SUPPORT)
        ->and($message->sender_id)->toBe($subscriber->id)
        ->and($message->sender_type)->toBe(SupportTicketSenderType::SUBSCRIBER);

    Notification::assertSentTo($admin, NewSupportTicketNotification::class);
    Notification::assertNotSentTo($subscriber, NewSupportTicketNotification::class);
});

it('generates deterministic unique references from inserted record IDs', function () {
    Notification::fake();

    $subscriber = User::factory()->subscriber()->create();
    $payload = [
        'subject' => 'Reference generation request',
        'priority' => 'medium',
        'message' => 'This message verifies unique ticket references.',
    ];

    $this->actingAs($subscriber)->post(route('support-tickets.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($subscriber)->post(route('support-tickets.store'), [
        ...$payload,
        'subject' => 'Second reference generation request',
    ])->assertSessionHasNoErrors();

    expect(SupportTicket::query()->orderBy('id')->pluck('reference')->all())->toBe([
        'TKT-100001',
        'TKT-100002',
    ]);
});

it('validates ticket fields and rejects visually empty markup', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->from(route('support-tickets.create'))->post(route('support-tickets.store'), [
        'subject' => 'bad',
        'priority' => 'critical',
        'category' => str_repeat('x', 101),
        'message' => '<p><br></p>',
    ])->assertRedirect(route('support-tickets.create'))
        ->assertSessionHasErrors(['subject', 'priority', 'category', 'message'], null, 'supportTicket');

    $this->assertDatabaseCount('support_tickets', 0);
});

it('escapes untrusted ticket and message content', function () {
    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber, ['subject' => '<script>alert("subject")</script>']);
    messageFor($ticket, $subscriber, ['body' => '<img src=x onerror=alert(1)> Hello']);

    $response = $this->actingAs($subscriber)->get(route('support-tickets.show', $ticket));

    $response->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
        ->assertDontSee('<script>', false)
        ->assertDontSee('<img src=x', false);
});

it('stores validated attachments privately and permits only their owner to download them', function () {
    Storage::fake('local');

    $subscriber = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->post(route('support-tickets.store'), [
        'subject' => 'Attachment download request',
        'priority' => 'medium',
        'category' => null,
        'message' => 'The requested diagnostic file is attached.',
        'attachments' => [UploadedFile::fake()->create('diagnostic.txt', 4, 'text/plain')],
    ])->assertSessionHasNoErrors();

    $ticket = SupportTicket::query()->sole();
    $attachment = SupportTicketAttachment::query()->sole();

    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->path)->toStartWith("support-tickets/{$ticket->id}/")
        ->and($attachment->path)->not->toContain('diagnostic.txt');

    $this->actingAs($subscriber)
        ->get(route('support-tickets.attachments.download', [$ticket, $attachment]))
        ->assertOk()
        ->assertDownload('diagnostic.txt')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs($otherSubscriber)
        ->get(route('support-tickets.attachments.download', [$ticket, $attachment]))
        ->assertNotFound();
});

it('rejects unsafe, excessive, oversized, and double-extension attachments', function () {
    Storage::fake('local');
    config()->set('support-tickets.attachments.max_kilobytes', 10);

    $subscriber = User::factory()->subscriber()->create();
    $valid = [
        'subject' => 'Attachment validation request',
        'priority' => 'medium',
        'message' => 'Please review these attachment validation details.',
    ];

    $cases = [
        [UploadedFile::fake()->create('payload.exe', 2, 'application/x-msdownload')],
        [UploadedFile::fake()->image('payload.php.jpg')],
        [UploadedFile::fake()->image('large.jpg')->size(11)],
        array_map(fn (int $index) => UploadedFile::fake()->create("file-{$index}.txt", 1, 'text/plain'), range(1, 6)),
    ];

    foreach ($cases as $attachments) {
        $this->actingAs($subscriber)->post(route('support-tickets.store'), [
            ...$valid,
            'attachments' => $attachments,
        ])->assertSessionHasErrors();
    }

    $this->assertDatabaseCount('support_tickets', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('stores original attachment names only as escaped display metadata', function () {
    Storage::fake('local');

    $subscriber = User::factory()->subscriber()->create();
    $displayName = '<img onerror=alert(1)>.txt';

    $this->actingAs($subscriber)->post(route('support-tickets.store'), [
        'subject' => 'Safe attachment filename request',
        'priority' => 'low',
        'message' => 'The attachment display name must remain escaped.',
        'attachments' => [UploadedFile::fake()->create($displayName, 1, 'text/plain')],
    ])->assertSessionHasNoErrors();

    $ticket = SupportTicket::query()->sole();
    expect(SupportTicketAttachment::query()->sole()->original_name)->toBe($displayName);

    $this->actingAs($subscriber)->get(route('support-tickets.show', $ticket))
        ->assertOk()
        ->assertSee('&lt;img onerror=alert(1)&gt;.txt', false)
        ->assertDontSee('<img onerror=alert(1)>', false);
});

it('rolls back ticket records and uploaded files when persistence fails', function () {
    Storage::fake('local');
    Notification::fake();

    $subscriber = User::factory()->subscriber()->create();
    User::factory()->admin()->create();
    SupportTicketAttachment::creating(fn () => throw new RuntimeException('Simulated database failure'));

    try {
        $this->actingAs($subscriber)->post(route('support-tickets.store'), [
            'subject' => 'Transaction cleanup request',
            'priority' => 'medium',
            'message' => 'This request must roll back completely.',
            'attachments' => [UploadedFile::fake()->create('details.txt', 2, 'text/plain')],
        ])->assertRedirect(route('support-tickets.create'))
            ->assertSessionHas('toast.message', 'Your ticket could not be created. Please try again.');
    } finally {
        SupportTicketAttachment::flushEventListeners();
    }

    $this->assertDatabaseCount('support_tickets', 0);
    $this->assertDatabaseCount('support_ticket_messages', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Notification::assertNothingSent();
});

it('adds subscriber replies with attachments and moves the ticket to awaiting support', function () {
    Storage::fake('local');
    Notification::fake();

    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $ticket = ticketFor($subscriber, [
        'status' => SupportTicketStatus::AWAITING_USER,
        'last_activity_at' => now()->subDay(),
    ]);

    $this->actingAs($subscriber)->post(route('support-tickets.replies.store', $ticket), [
        'message' => 'Here are the two files you requested.',
        'sender_id' => $admin->id,
        'sender_type' => SupportTicketSenderType::STAFF->value,
        'status' => SupportTicketStatus::CLOSED->value,
        'attachments' => [
            UploadedFile::fake()->create('one.txt', 1, 'text/plain'),
            UploadedFile::fake()->create('two.txt', 1, 'text/plain'),
        ],
    ])->assertRedirect(route('support-tickets.show', $ticket))
        ->assertSessionHas('toast.message', 'Reply sent successfully.');

    $ticket->refresh();
    $message = $ticket->messages()->sole();
    expect($ticket->status)->toBe(SupportTicketStatus::AWAITING_SUPPORT)
        ->and($ticket->last_activity_at->greaterThan(now()->subMinute()))->toBeTrue()
        ->and($message->sender_id)->toBe($subscriber->id)
        ->and($message->sender_type)->toBe(SupportTicketSenderType::SUBSCRIBER)
        ->and($message->attachments()->count())->toBe(2);
    Notification::assertSentTo($admin, SupportTicketSubscriberRepliedNotification::class);
});

it('stores multiple follow-up replies for an open ticket', function () {
    Notification::fake();

    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber);

    foreach (['First follow-up reply.', 'Second follow-up reply.'] as $body) {
        $this->actingAs($subscriber)->post(route('support-tickets.replies.store', $ticket), [
            'message' => $body,
        ])->assertSessionHasNoErrors();
    }

    expect($ticket->messages()->orderBy('id')->pluck('body')->all())->toBe([
        'First follow-up reply.',
        'Second follow-up reply.',
    ]);
});

it('blocks subscriber replies after a ticket is resolved or closed', function (SupportTicketStatus $status) {
    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber, ['status' => $status]);

    $this->actingAs($subscriber)->post(route('support-tickets.replies.store', $ticket), [
        'message' => 'This reply must not be accepted.',
    ])->assertSessionHasErrors(['message'], null, 'supportReply');

    $this->actingAs($subscriber)->get(route('support-tickets.show', $ticket))
        ->assertOk()
        ->assertSee('This ticket is closed. Open a new ticket if you still need help.')
        ->assertDontSee('Send Reply');

    $this->assertDatabaseCount('support_ticket_messages', 0);
})->with([SupportTicketStatus::RESOLVED, SupportTicketStatus::CLOSED]);

it('returns 404 for cross-subscriber ticket viewing, replying, and attachment access', function () {
    $owner = User::factory()->subscriber()->create();
    $attacker = User::factory()->subscriber()->create();
    $ticket = ticketFor($owner);
    $message = messageFor($ticket, $owner);
    $attachment = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $message->id,
        'disk' => 'local',
        'path' => "support-tickets/{$ticket->id}/{$message->id}/secret.txt",
        'original_name' => 'secret.txt',
        'mime_type' => 'text/plain',
        'size' => 6,
    ]);

    $this->actingAs($attacker)->get(route('support-tickets.show', $ticket))->assertNotFound();
    $this->actingAs($attacker)->post(route('support-tickets.replies.store', $ticket), [
        'message' => 'Attempted unauthorized reply.',
    ])->assertNotFound();
    $this->actingAs($attacker)
        ->get(route('support-tickets.attachments.download', [$ticket, $attachment]))
        ->assertNotFound();
});

it('rejects attachment records outside the ticket and manipulated storage paths', function () {
    Storage::fake('local');

    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber);
    $message = messageFor($ticket, $subscriber);
    $otherTicket = ticketFor($subscriber);
    $otherMessage = messageFor($otherTicket, $subscriber);
    $otherAttachment = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $otherMessage->id,
        'disk' => 'local',
        'path' => "support-tickets/{$otherTicket->id}/{$otherMessage->id}/other.txt",
        'original_name' => 'other.txt',
        'mime_type' => 'text/plain',
        'size' => 5,
    ]);
    $tampered = SupportTicketAttachment::query()->create([
        'support_ticket_message_id' => $message->id,
        'disk' => 'local',
        'path' => '../private-secret.txt',
        'original_name' => 'private-secret.txt',
        'mime_type' => 'text/plain',
        'size' => 5,
    ]);
    Storage::disk('local')->put('private-secret.txt', 'secret');

    $this->actingAs($subscriber)
        ->get(route('support-tickets.attachments.download', [$ticket, $otherAttachment]))
        ->assertNotFound();
    $this->actingAs($subscriber)
        ->get(route('support-tickets.attachments.download', [$ticket, $tampered]))
        ->assertNotFound();
});

it('shows a staff marker only for messages sent by a current administrator', function () {
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create(['name' => 'Support Operator']);
    $ticket = ticketFor($subscriber);
    messageFor($ticket, $admin, [
        'sender_type' => SupportTicketSenderType::STAFF,
        'body' => 'A verified response from staff.',
    ]);
    messageFor($ticket, $subscriber, [
        'sender_type' => SupportTicketSenderType::STAFF,
        'body' => 'A forged staff sender snapshot.',
    ]);

    $content = $this->actingAs($subscriber)->get(route('support-tickets.show', $ticket))
        ->assertOk()
        ->assertSee('A verified response from staff.')
        ->assertSee('A forged staff sender snapshot.')
        ->getContent();

    expect(substr_count($content, '>Staff<'))->toBe(1);
});

it('searches, filters, safely sorts, and paginates subscriber tickets', function () {
    $subscriber = User::factory()->subscriber()->create();
    SupportTicket::factory()->count(16)->for($subscriber, 'requester')->create();
    $match = ticketFor($subscriber, [
        'subject' => 'Unique billing subject',
        'category' => 'Account Access',
        'priority' => SupportTicketPriority::URGENT,
        'status' => SupportTicketStatus::AWAITING_USER,
    ]);

    $search = $this->actingAs($subscriber)->get(route('support-tickets.index', [
        'search' => 'Account Access',
        'priority' => 'urgent',
        'status' => 'awaiting_user',
        'sort' => 'subject',
        'direction' => 'asc',
        'per_page' => 10,
    ]))->assertOk()->assertSee($match->subject);

    $tickets = $search->viewData('tickets');
    expect($tickets->total())->toBe(1)
        ->and($tickets->perPage())->toBe(10)
        ->and($tickets->nextPageUrl())->toBeNull();

    $this->actingAs($subscriber)->get(route('support-tickets.index', ['search' => $match->reference]))
        ->assertOk()
        ->assertSee($match->subject);
    $this->actingAs($subscriber)->get(route('support-tickets.index', ['search' => 'Unique billing']))
        ->assertOk()
        ->assertSee($match->reference);

    $page = $this->actingAs($subscriber)->get(route('support-tickets.index', [
        'status' => 'awaiting_support',
        'per_page' => 10,
    ]))->assertOk()->viewData('tickets');
    expect($page->perPage())->toBe(10)
        ->and($page->nextPageUrl())->toContain('status=awaiting_support')
        ->and($page->nextPageUrl())->toContain('per_page=10');

    $invalidSort = $this->actingAs($subscriber)->get(route('support-tickets.index', [
        'sort' => 'requester_id desc; drop table users',
        'direction' => 'sideways',
        'per_page' => 999,
    ]))->assertOk();
    expect($invalidSort->viewData('filters')['sort'])->toBe('last_activity_at')
        ->and($invalidSort->viewData('filters')['direction'])->toBe('desc')
        ->and($invalidSort->viewData('tickets')->perPage())->toBe(15);
});

it('orders conversation messages newest first', function () {
    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber);
    messageFor($ticket, $subscriber, ['body' => 'Oldest message', 'created_at' => now()->subHours(2)]);
    messageFor($ticket, $subscriber, ['body' => 'Newest message', 'created_at' => now()]);

    $response = $this->actingAs($subscriber)->get(route('support-tickets.show', $ticket))->assertOk();

    $response->assertSeeInOrder(['Newest message', 'Oldest message']);
});

it('creates database notifications without storing message bodies or attachment paths', function () {
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($subscriber)->post(route('support-tickets.store'), [
        'subject' => 'Notification payload request',
        'priority' => 'low',
        'message' => 'Sensitive body must not be copied into notifications.',
    ])->assertSessionHasNoErrors();

    $notification = $admin->notifications()->sole();
    expect($notification->data)->toHaveKeys(['activity', 'ticket_id', 'reference', 'subject', 'priority'])
        ->and($notification->data)->not->toHaveKeys(['message', 'body', 'path', 'requester_email']);
});

it('keeps committed tickets when notification delivery fails', function () {
    Event::forget(SupportTicketCreated::class);
    Event::listen(SupportTicketCreated::class, fn () => throw new RuntimeException('Notification transport failed'));

    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->post(route('support-tickets.store'), [
        'subject' => 'Notification failure request',
        'priority' => 'low',
        'message' => 'The ticket should survive notification failure.',
    ])->assertSessionHas('toast.message', 'Ticket created successfully.');

    $this->assertDatabaseCount('support_tickets', 1);
    $this->assertDatabaseCount('support_ticket_messages', 1);
});

it('rate limits ticket creation and subscriber replies', function () {
    Notification::fake();
    config()->set('support-tickets.rate_limits.create_per_minute', 1);
    config()->set('support-tickets.rate_limits.reply_per_minute', 1);

    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber);
    RateLimiter::clear('support-ticket-create:'.$subscriber->id);
    RateLimiter::clear('support-ticket-reply:'.$subscriber->id);

    $ticketPayload = [
        'subject' => 'First rate limited ticket',
        'priority' => 'medium',
        'message' => 'This is the first allowed support request.',
    ];

    $this->actingAs($subscriber)->post(route('support-tickets.store'), $ticketPayload)
        ->assertSessionHas('toast.message', 'Ticket created successfully.');
    $this->actingAs($subscriber)->post(route('support-tickets.store'), [
        ...$ticketPayload,
        'subject' => 'Second rate limited ticket',
    ])->assertSessionHas('toast.message', 'Please wait before submitting another support ticket.');

    $this->actingAs($subscriber)->post(route('support-tickets.replies.store', $ticket), [
        'message' => 'This is the first allowed reply.',
    ])->assertSessionHas('toast.message', 'Reply sent successfully.');
    $this->actingAs($subscriber)->post(route('support-tickets.replies.store', $ticket), [
        'message' => 'This reply should be rate limited.',
    ])->assertSessionHas('toast.message', 'Please wait before sending another reply.');

    expect(SupportTicket::query()->count())->toBe(2)
        ->and($ticket->messages()->count())->toBe(1);
});

it('keeps support state-changing endpoints unavailable through GET', function () {
    $subscriber = User::factory()->subscriber()->create();
    $ticket = ticketFor($subscriber);

    $this->actingAs($subscriber)->get('/settings/support/'.$ticket->reference.'/replies')->assertMethodNotAllowed();
    $this->actingAs($subscriber)->get('/settings/support/store')->assertNotFound();
});
