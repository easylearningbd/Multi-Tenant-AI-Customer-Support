# Bot foundation

The bot subsystem currently provides persistence, configuration defaults, subscriber authorization, the owner-scoped bot list, transactional draft creation, and the subscriber settings workflow. It does not call an AI provider, ingest knowledge, or serve a public widget.

## Tenant boundary

The application currently has no workspace or membership tables. Its existing subscriptions, payments, plan limits, and subscriber authorization use the subscriber `users.id` as the billing-owner boundary. Bots therefore use `bots.user_id` and must be queried through `Bot::ownedBy($user)` or `$user->bots()`, then authorized through `BotPolicy`.

Do not accept `user_id` from a request. Ownership and bot parent identifiers are not mass assignable. The public ULID is used for future route binding; internal numeric IDs are not public widget credentials.

Before shared workspaces or invited members are implemented, introduce the canonical workspace/membership model and migrate bot ownership deliberately rather than adding a second tenant system.

## Persistence

- `bots`: identity, opaque public ULID, owner, tenant-unique slug, active state, and soft deletion.
- `bot_settings`: one settings row per bot, including controlled tone and AI tuning values.
- `bot_starter_questions`: ordered questions with a six-item application and database position limit.
- `bot_prechat_fields`: ordered fields with controlled field types, server-controlled standard keys, JSON select options, and a six-item application and database position limit.

Deleting a user is restricted while bots exist. Deleting a bot uses soft deletion, so child rows remain available for later retention and cleanup decisions.

## Configuration

AI and bot defaults live in `config/neuraldesk.php`. Configure OpenAI through the documented `OPENAI_*` variables in `.env.example`; application code must read Laravel configuration and must never read `env()` directly. Rendering non-AI pages does not require an API key.

`CreateDefaultBotSettings` creates the single settings row transactionally and idempotently. `BotConfigurationRules` and `BotConfigurationService` centralize supported values and limits.

`UpdateBotConfiguration` explicitly maps validated identity and setting values, then replaces ordered starter questions and pre-chat fields in one transaction. Standard visitor fields retain server-controlled keys and field types. Custom keys are generated server-side. Subscriber-selectable languages and AI model overrides come from controlled configuration lists; an empty model allowlist means the platform default is mandatory.

`CreateBot` locks the subscriber owner row before resolving the current subscription, counting current bots, and applying `chatbots_limit`. It then creates an inactive draft, reserves an owner-scoped slug, and creates default settings in the same transaction. Zero continues to mean unlimited through the existing subscription and plan-limit domain methods.

Soft-deleted bots do not consume current capacity, but their slugs remain reserved. Any future restore action must recheck the current plan limit while holding the same owner lock.

## Subscriber routes

- `GET /bots`: owner-scoped, paginated bot cards and an empty state.
- `POST /bots`: validated bot creation; browser-supplied owner, public ID, slug, and active state are ignored.
- `GET /bots/{public_id}/setup`: compatibility redirect to the owner-scoped settings page.
- `GET /bots/{public_id}/settings`: identity, behavior, greeting, starter questions, pre-chat fields, response tuning, status, and safe module placeholders.
- `PUT /bots/{public_id}/settings`: atomic validated configuration update.
- `DELETE /bots/{public_id}`: deactivates and soft-deletes the bot while retaining related configuration for historical integrity and recovery.

Knowledge training is implemented behind the owner-scoped training routes documented in `docs/KNOWLEDGE_TRAINING.md`. Integrations, knowledge-base assignment, and embed actions remain disabled until their corresponding subsystems exist.

No OpenAI client dependency is installed in Phase 1.

## Queues and private files

The existing database queue tables and database queue connection are reused. Named queues are configured for `knowledge-ingestion`, `embeddings`, `ai-responses`, and `notifications`.

Knowledge source files will use the private `knowledge` disk rooted at `storage/app/private/knowledge-sources`. The disk does not serve public URLs and reports storage failures. Upload and ingestion behavior is deferred to the training phase.

## Vector backend decision

MySQL is the final approved vector backend for the current product architecture. Do not install or request a decision about an external vector database in later phases unless the product owner explicitly changes this decision. `VectorStoreInterface` preserves a controlled migration boundary without configuring or referencing a second backend.
