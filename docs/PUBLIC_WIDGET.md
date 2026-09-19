# Public widget delivery

Phase 7 delivers the versioned, dependency-free widget loader, isolated chat frame, hosted chat page, demo page, and token-authenticated public chat API. The loader creates a sandboxed iframe so customer CSS and JavaScript cannot alter the chat interface. The same frame UI serves embedded and hosted chat responsively and exposes an accessible live message log, keyboard-operable controls, starter questions, configured pre-chat fields, and human handoff.

## Public routes

- `GET /widgets/v1/{widget}/loader.js` returns the cacheable loader.
- `GET /widgets/v1/{widget}/frame` renders an origin-authorized embedded frame.
- `GET /chat/{widget}` renders the hosted chat page.
- `GET /widgets/demo/{widget}` renders the isolated customer-site simulation.
- `/api/widgets/v1/{widget}/*` bootstraps visitor sessions, saves pre-chat values, accepts messages, polls conversations, and requests handoff.

Widget identifiers are opaque ULIDs. Public routes resolve an enabled widget, active bot, tenant owner, and subscription that currently grants entitlements. Tenant IDs, bot database IDs, subscription metadata, embeddings, provider configuration, prompts, and secrets are never serialized to the browser.

## Origin and session security

Customer origins are normalized and stored in `widget_domains` as exact scheme, host, and optional port values. Paths, credentials, query strings, fragments, and non-HTTP schemes are rejected. The embedded frame validates the request origin, emits a matching `frame-ancestors` policy, and creates a short-lived encrypted access proof bound to the widget, origin, and channel.

The bootstrap endpoint verifies that proof again and returns a random visitor token once. Only its SHA-256 digest is stored. Subsequent API calls resolve the visitor session using the bearer token together with the widget, tenant, bot, and expiration filters. Conversation reads and writes additionally require the same visitor-session ID. Pre-chat values use Laravel's encrypted model cast and are never included in public responses.

Public API routes are excluded from cookie CSRF verification because they do not authorize with the Laravel browser session. They require the proof or opaque bearer token and are rate-limited by widget, address, and token hash. APIs remain same-origin to the sandboxed frame, avoiding permissive cross-origin credential policies.

## Conversation and AI behavior

Messages use `QueueConversationMessage`, which atomically persists a tenant- and bot-scoped conversation/message, reserves one plan AI-answer unit, and queues `GenerateConversationReply`. The Phase 5 pipeline performs MySQL RAG retrieval, strict-grounding fallback, citations, usage commit/release, and configured handoff. Public requests never call OpenAI from the browser.

Run workers for the existing AI queue configuration:

```bash
php artisan queue:work --queue=ai-responses,default
```

## Configuration

The following optional environment values control public delivery and have safe defaults in `config/neuraldesk.php`:

```env
WIDGET_MAXIMUM_ORIGINS=25
WIDGET_SESSION_LIFETIME_MINUTES=720
WIDGET_PROOF_LIFETIME_MINUTES=10
WIDGET_BOOTSTRAP_RATE_PER_MINUTE=30
WIDGET_MESSAGE_RATE_PER_MINUTE=12
WIDGET_POLL_RATE_PER_MINUTE=60
WIDGET_PRECHAT_VALUE_MAX=1000
```

`APP_URL` must be the externally reachable HTTPS application URL in production because loader, frame, hosted-chat, postMessage, and CSP values are generated from it.
