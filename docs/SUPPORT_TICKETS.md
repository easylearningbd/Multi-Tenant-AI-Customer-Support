# Subscriber support tickets

The subscriber support module uses `support_tickets`, `support_ticket_messages`, and
`support_ticket_attachments`. Messages support subscriber and staff sender types so the future
Admin ticket interface can reply without a schema change. The current application has no
workspace or membership tables, so `support_tickets.requester_id` is the explicit isolation
boundary. Add a workspace foreign key and compound indexes when the tenancy subsystem is
introduced.

## Setup

Apply the schema with:

```bash
php artisan migrate
```

Attachments use Laravel's private `local` disk by default and are available only through the
authorized download controller. They are stored below `support-tickets/{ticket}/{message}` with
server-generated filenames. Configure another private disk without changing application code:

```env
SUPPORT_TICKET_ATTACHMENT_DISK=local
SUPPORT_TICKET_ATTACHMENT_MAX_KB=10240
SUPPORT_TICKET_CREATE_RATE_PER_MINUTE=5
SUPPORT_TICKET_REPLY_RATE_PER_MINUTE=20
```

New tickets and subscriber replies dispatch queued database notifications to accounts with the
platform `admin` role. In production, run a worker that includes the notifications queue:

```bash
php artisan queue:work --queue=notifications,default
```

The subscriber routes are under `/settings/support` and require `auth` plus `role:user`.
Cross-subscriber references return 404. The Admin list, assignment, status transitions, and reply
interface remain a separate future module.
