# Subscriber conversations inbox

The subscriber inbox is available at `/dashboard/conversations` for authenticated subscriber accounts. It aggregates conversations across every bot owned by the subscriber tenant and applies `user_id`, `bot_id`, and conversation UUID checks to list, message, action, polling, and attachment requests.

## Conversation state

Conversation status and handling mode are separate concerns:

- Open conversations can run in `ai` or `manual` handling mode.
- A handoff changes the status to `needs_human`, sets manual mode, records `handoff_requested_at`, creates a system event, and sends a database notification.
- Resolving stops replies and records the subscriber and timestamp.
- Reopening preserves history and the selected handling mode.
- Archiving removes a conversation from the normal inbox while retaining all messages.
- Deleting from the inbox is a soft delete. Permanent retention cleanup is outside this feature.

Mode changes, state changes, and agent replies lock the tenant-scoped conversation row inside a database transaction. Agent replies require manual mode and a UUID idempotency key. Before an AI job publishes an answer, it reloads the same tenant, bot, conversation, and visitor message and verifies that the conversation is still open in AI mode and that no agent has answered the visitor message.

## Delivery and polling

The project does not currently configure Reverb, Echo, SSE, or another WebSocket service, so the inbox follows the existing widget convention and uses authenticated incremental AJAX polling. Poll requests include the last known message UUID and return only later messages from the selected tenant conversation. Polling slows while the browser tab is hidden and does not force-scroll an agent who is reading older messages.

Visitor messages in AI mode continue through `GenerateConversationReply`. Visitor messages in manual mode are stored and exposed to the inbox without dispatching OpenAI work. Agent text replies are returned by the existing opaque-token widget conversation endpoint, so only the matching visitor session receives them.

Run queue workers for AI answers and handoff notifications:

```bash
php artisan queue:work --queue=ai-responses,notifications,default
```

## Private attachments

Subscriber reply attachments are stored on the private `conversation_attachments` disk with randomized filenames. Validation checks the server-observed MIME type, extension, size, and dangerous filename patterns. Downloads are served through an authenticated, tenant-authorized controller and never reveal the storage path.

The public widget intentionally receives agent text only. Private agent attachments are not exposed through the public widget API because the project has no visitor-scoped, expiring attachment-download contract yet.

Configure the private disk and inbox behavior with these optional values:

```env
CONVERSATION_INBOX_PAGE_SIZE=20
CONVERSATION_MESSAGE_PAGE_SIZE=50
CONVERSATION_POLL_SECONDS=4
CONVERSATION_ATTACHMENT_DISK=conversation_attachments
CONVERSATION_ATTACHMENT_MAX_KB=10240
CONVERSATION_MAXIMUM_ATTACHMENTS=5
```

No public storage link is required. The default local path is `storage/app/private/conversation-attachments`.

## Current authorization model

The repository currently has subscriber owners and separate admin users, but no workspace membership or team-permission tables. `ConversationPolicy` therefore grants inbox abilities only to the owning subscriber and rejects admins and other subscribers. The policy methods are split by view, reply, mode, status, archive, delete, and attachment download so a future membership system can add granular permissions without changing controllers or routes.
