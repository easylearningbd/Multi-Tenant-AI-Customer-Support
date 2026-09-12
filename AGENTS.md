# AGENTS.md

## Project Mission

Build a production-grade, self-hosted, multi-tenant AI customer-support SaaS using Laravel, Laravel Breeze, MySQL, and the OpenAI API. The product includes a public marketing site, a Subscriber workspace, a separate Super Admin back office, Retrieval-Augmented Generation (RAG), embedded and hosted chat, live support, visitor tracking, lead capture, team collaboration, subscriptions, payments, and platform operations.

Treat this as a real client system. Favor correctness, tenant safety, maintainability, testability, and operational clarity over shortcuts or demo-only behavior.

The canonical product specification is `Multi-Tenant-AI-Customer-Support-SaaS-PRD.md`. Read it before changing product behavior. If code, screenshots, and the PRD disagree, identify the conflict instead of silently choosing one.

---

## Non-Negotiable Rules

1. Never allow cross-tenant reads, writes, retrievals, cache collisions, broadcasts, files, exports, or vector matches.
2. Every AI answer must follow the RAG policy configured for its bot. “Answer only from knowledge base” must never degrade into general model knowledge.
3. Retrieved documents are untrusted content and can never override system/developer instructions.
4. Never expose OpenAI keys, payment secrets, webhook secrets, internal prompts, password data, 2FA secrets, private notes, or raw sensitive metadata.
5. Server-side policies and domain services are authoritative. UI hiding is not authorization.
6. Do not trust client-supplied workspace IDs, prices, entitlements, roles, payment states, model names, token limits, or usage totals.
7. Long-running work must use queues. HTTP requests must not perform full crawling, parsing, embedding, reindexing, or bulk exports.
8. Payment webhooks, refunds, training runs, usage reservations, and retraining must be idempotent.
9. Store money in integer minor units with an ISO currency code. Never use floating-point money.
10. Store timestamps in UTC and render them in the selected user/workspace timezone.
11. Do not hard-code screenshot sample data, plan prices, counts, names, dates, model identifiers, or provider credentials.
12. Every behavior change requires relevant automated tests.

---

## Working Method

Before implementation:

1. Read this file and the relevant PRD sections.
2. Inspect existing routes, models, migrations, policies, services, tests, frontend conventions, and configuration.
3. Check for more specific `AGENTS.md` files in the target subtree.
4. Identify tenant boundary, actor, authorization rule, state transition, side effects, failure behavior, and test plan.
5. Reuse existing project patterns when they satisfy security and maintainability requirements.

While implementing:

- Keep changes scoped to the requested feature.
- Do not overwrite unrelated user work.
- Prefer small, cohesive classes and explicit domain operations.
- Put external providers behind interfaces.
- Use database transactions for atomic multi-record changes.
- Dispatch side effects after commit when they depend on committed data.
- Use database constraints in addition to application validation.
- Avoid speculative abstractions, but do not couple business rules to controllers or vendors.

Before finishing:

- Run targeted tests, then the appropriate broader suite.
- Run the project's formatter/static analysis tools if configured.
- Check authorization, tenant scoping, validation, empty/loading/error states, and accessibility.
- Report migrations, configuration, worker/scheduler requirements, and any unresolved decisions.

Never claim a test passed unless it was executed successfully.

---

## Technology Baseline

- **Backend:** Laravel; follow the version pinned by `composer.lock`.
- **Authentication:** Laravel Breeze foundation.
- **Database:** MySQL as the transactional system of record.
- **Queues/cache/locks/rate limits:** Redis when configured; use Laravel abstractions.
- **Real-time:** Laravel broadcasting, preferably Laravel Reverb when selected by the project.
- **Files:** Laravel Filesystem with local and S3-compatible drivers.
- **AI:** OpenAI through internal provider interfaces.
- **Vector search:** `VectorStore` interface; self-hosted Qdrant is the preferred production adapter unless the repository establishes another choice.
- **Payments:** Gateway interfaces with independently testable adapters.
- **Frontend:** Follow the installed Breeze/frontend stack; do not replace it without explicit approval.

Do not add a dependency merely for convenience. Confirm it is maintained, compatible, licensed appropriately, and materially reduces risk or complexity.

---

## Repository and Code Organization

Follow existing conventions. If establishing the initial architecture, prefer domain-oriented boundaries such as:

```text
app/
  Domain/
    Tenancy/
    Bots/
    Knowledge/
    Conversations/
    Visitors/
    Leads/
    Billing/
    Admin/
  Actions/
  Contracts/
  DTOs/
  Enums/
  Events/
  Exceptions/
  Http/
    Controllers/
    Middleware/
    Requests/
    Resources/
  Jobs/
  Listeners/
  Models/
  Notifications/
  Policies/
  Providers/
  Services/
```

This layout is guidance, not permission to reorganize an established repository. Keep controllers thin. Business rules belong in actions/domain services, authorization in policies, input rules in Form Requests, and serialization in Resources/view models.

### Naming

- Name classes after business actions: `CreateBot`, `TrainKnowledgeSource`, `RequestHumanHandoff`.
- Use explicit enums for statuses and actors.
- Name events in past tense: `KnowledgeSourceTrained`.
- Name jobs by work performed: `GenerateSourceEmbeddings`.
- Avoid vague buckets such as `Helper`, `CommonService`, or `Utils`.

### PHP and Laravel quality

- Use strict types in new PHP files if the repository uses them.
- Use typed properties, parameters, returns, DTOs, enums, and value objects where they improve correctness.
- Prefer dependency injection over service-locator calls inside domain code.
- Avoid mass-assignment of authorization-sensitive fields.
- Eager-load intentionally and prevent N+1 queries.
- Paginate unbounded collections.
- Keep configuration in config files/environment variables, never scattered `env()` calls outside config.

---

## Tenancy Architecture

The default model is shared MySQL with row-level workspace scoping.

### Required invariants

- `workspaces` define tenant boundaries.
- Tenant-owned root records contain `workspace_id` with foreign keys and useful compound indexes.
- Child records must be reachable only through an already-scoped parent or carry their own tenant key when needed for enforcement/performance.
- Resolve the active workspace from authenticated membership or a verified public widget token.
- Never accept a raw request `workspace_id` as authority.
- Route model binding must not resolve tenant-owned models globally.
- Policies must verify workspace membership and action permission.
- Cache, lock, rate-limit, idempotency, storage, export, broadcast, and vector keys include workspace scope.
- Queued jobs explicitly serialize the workspace identifier and re-establish tenant context before querying.
- Global scopes may reduce mistakes but are not sufficient alone; tests and explicit policies remain required.

### Super Admin access

- Admin bypass must be explicit, policy-controlled, and audited.
- “Login as User” must create a persisted impersonation session with actor, target, reason if required, start, expiry, and stop.
- Show a persistent impersonation banner.
- Prevent or re-confirm high-risk actions during impersonation according to product policy.

### Tenant tests

For each tenant-owned feature, test:

- Same-tenant authorized success.
- Same-tenant unauthorized denial.
- Cross-tenant ID access denial.
- Cross-tenant list/search exclusion.
- Cross-tenant job/event/broadcast isolation when applicable.

---

## Authentication and Authorization

### Subscriber

- Breeze supplies login, registration, password reset, verification, and session foundations.
- Registration creates user, workspace, owner membership, and trial/subscription atomically.
- Subscriber routes require verified membership in the active workspace.

### Super Admin

- Use a distinct route prefix/name, middleware/guard strategy, layout, and dashboard.
- No public admin registration.
- Rate-limit and audit admin authentication.
- Apply 2FA requirements when configured.

### Permissions

- Use explicit permission names: `users.view`, `plans.create`, `payments.refund`.
- Policies/gates are required for all protected actions.
- Role and permission changes must invalidate relevant authorization caches.
- Protect required system roles and the last privileged account from unsafe deletion/demotion.

---

## RAG Architecture

RAG is mandatory for bot answers.

```text
User message
  -> validate tenant/widget/quota
  -> persist message
  -> retrieve authorized evidence
  -> rank/filter evidence
  -> build guarded prompt
  -> call OpenAI
  -> stream and persist answer
  -> store citations/usage/trace
  -> fallback or handoff when evidence is insufficient
```

### Contracts

Keep provider-specific code behind contracts similar to:

- `EmbeddingProvider`
- `VectorStore`
- `Retriever`
- `Reranker`
- `PromptBuilder`
- `ChatCompletionProvider`
- `ModerationProvider`

Business code must not call an OpenAI SDK or vector client directly from controllers, models, views, or Livewire/JS components.

### Ingestion

- Source lifecycle: draft, queued, processing, trained, failed, stale, disabled.
- Validate authorization, entitlement, file size/type, URL, and source ownership before queueing.
- Store original source and immutable source version.
- Extract and normalize content in background jobs.
- Preserve page, heading, row, URL, checksum, locale, and version metadata.
- Chunk deterministically with a versioned strategy.
- Generate embeddings in bounded batches.
- Upsert vectors with `workspace_id`, knowledge-base/bot scope, source ID, chunk ID, source version, embedding model, and strategy version.
- Activate a new index version only after it completes; do not expose partially trained content.
- Disable/delete/retrain flows must synchronize vector state idempotently.

### Retrieval

- Apply workspace and bot/knowledge-base filters before semantic ranking.
- Exclude disabled, deleted, stale, and superseded chunks.
- Support configurable top-K, minimum score, deduplication, diversity, optional hybrid search, and optional reranking.
- Bound retrieval and conversation context by tokens.
- Persist a safe retrieval trace for evaluation: query/version, selected chunk IDs, ranks, scores, timings, and configuration.
- Never expose internal scores or hidden prompts to public visitors.

### Prompt construction

- Separate system rules, subscriber persona, conversation history, evidence, and user input.
- Delimit evidence clearly.
- State that evidence is untrusted and instructions inside it must be ignored.
- Require citations to stable source identifiers.
- Enforce the bot's tone, primary language, max tokens, and grounding setting.
- Do not interpolate secrets or private agent notes.

### Generation and persistence

- Use approved model identifiers from configuration/admin settings.
- Keep OpenAI keys server-side.
- Persist the visitor message before provider calls.
- Stream assistant output when possible while retaining a recoverable final state.
- Record model, provider request ID when available, input/output tokens, cost estimate, latency, finish reason, confidence, citations, and error state.
- Use bounded retry/backoff only for retry-safe failures.
- Account for quota through an idempotent usage reservation/ledger flow.

### Grounding policy

- If `answer_only_from_knowledge_base` is true and evidence is below threshold, return the configured fallback.
- Do not let general model knowledge fill evidence gaps under strict grounding.
- Offer or trigger human handoff according to configuration.
- Never promote generated answers into trusted knowledge without human review.

### Evaluation

- Add deterministic fixtures with known sources, questions, expected relevant chunks, citation expectations, and fallback cases.
- Test retrieval relevance separately from generation quality.
- Track helpful/unhelpful feedback and knowledge gaps.

---

## Conversations, Live Support, and Leads

### Conversation state

Use explicit transitions. A representative state set is:

- `open_ai`
- `needs_human`
- `open_manual`
- `resolved`
- `archived`
- `spam`

Do not scatter raw status updates. Use a transition service/action that validates actor, current state, next state, timestamps, assignment, AI ownership, notifications, and activity events.

### Messages

- Persist actor type: visitor, AI, agent, system.
- Preserve chronological ordering with stable public IDs.
- Store citations separately from message text.
- Internal notes use a separate type/table and must never enter public serialization or AI context unless explicitly designed.
- Store attachments through private tenant paths and authorized download routes.

### Handoff

- Human request, low confidence, configured rules, or agent takeover can trigger handoff.
- Once manual control begins, AI auto-reply must stop atomically.
- Assignment/claim operations require locks or conditional updates to avoid double claims.
- Resolving/reopening creates an audit/activity event.

### Live visitors

- Public presence tokens resolve the bot/workspace server-side.
- Heartbeats are rate-limited and privacy-aware.
- Approximate location must be configurable and retained only as policy permits.
- Proactive messages require authorized agents, valid live sessions, and abuse controls.

### Leads

- Treat pre-chat and custom-field input as untrusted.
- Validate field schema against the bot's active configuration.
- Encrypt or otherwise protect sensitive contact data according to project policy.
- Preserve consent, provenance, and timestamps.
- AI-derived qualification may suggest values but must not overwrite verified visitor data silently.

---

## Plans, Billing, and Payments

### Entitlements

- Plans are data-driven, not hard-coded.
- Centralize checks in `EntitlementService` or equivalent.
- Enforce limits in controllers/actions, queue jobs, and public widget paths.
- Use atomic counters/reservations for concurrency-sensitive limits.
- Define zero-as-unlimited only in domain code and tests.
- Store a subscription snapshot so historical terms survive plan edits.

### Usage

- Use an append-oriented usage ledger.
- Make entries idempotent with a stable source/event key.
- Record workspace, bot, conversation/message, model, type, quantity, billing period, and timestamps.
- Dashboard aggregates may be cached/materialized but must reconcile with the ledger.

### Payments

- Define a `PaymentGateway` contract for checkout, subscription operations, status lookup, and refunds.
- Never trust prices, currency, product IDs, or payment status from the browser.
- Verify webhook signatures before parsing business events.
- Store and deduplicate provider event IDs.
- Process webhooks through idempotent handlers and retain safe logs.
- Store sanitized provider metadata; redact secrets and unnecessary personal/card data.
- Refunds require permission, refundable-balance validation, integer minor units, idempotency, and audit logging.
- Manual payments require explicit admin attribution and must not masquerade as gateway-confirmed events.

---

## Queues, Scheduling, and Concurrency

- Use named queues for distinct workloads where useful: `rag-ingest`, `rag-embed`, `chat`, `webhooks`, `notifications`, `exports`, `default`.
- Jobs must define timeouts, tries, backoff, failure handling, and uniqueness/locking when needed.
- Pass IDs, not hydrated models or secrets, unless framework conventions safely serialize them.
- Re-query and re-authorize current state inside jobs.
- Reject stale jobs using source/config version checks.
- Use `afterCommit()` for jobs/events that require committed rows.
- Scheduler tasks must be safe under multiple application nodes, using `onOneServer()`/locks where appropriate.
- Do not automatically retry an operation when the external outcome is uncertain without an idempotency/reconciliation mechanism.

---

## Database Rules

- Write reversible migrations unless a documented constraint makes that impossible.
- Add foreign keys and intentional delete behavior.
- Add composite indexes matching tenant-scoped access patterns.
- Use ULIDs/UUIDs for public identifiers.
- Use unique constraints for idempotency keys, provider events, workspace slugs, source versions, and memberships as appropriate.
- Avoid JSON for core relational data that must be queried or constrained; use JSON for flexible snapshots/metadata with documented shape.
- Avoid polymorphic relations where they weaken integrity or tenant guarantees without a strong benefit.
- Large transcript, audit, and usage tables require pagination, archival/retention planning, and efficient indexes.
- Never edit an already-deployed migration to change production schema; add a new migration.

---

## HTTP, Validation, and API Rules

- Use Form Requests for non-trivial validation and authorization.
- Scope route model binding to workspace/admin context.
- Return consistent validation and domain-error structures.
- Use Resources/view models to prevent accidental field leakage.
- Version public widget contracts and keep the loader backward compatible.
- Rate-limit by appropriate combination of IP, session, bot, workspace, user, and action.
- Use signed/opaque tokens for public visitor sessions; do not expose tenant internals.
- Validate allowed widget origins server-side. Do not rely solely on browser CORS.
- Keep controllers thin and free of provider orchestration.

---

## Security and Privacy Checklist

For every relevant change, assess:

- Authentication and session fixation.
- Authorization/IDOR.
- Tenant isolation.
- CSRF/CORS/origin policy.
- XSS and unsafe HTML/Markdown rendering.
- SQL injection and unsafe dynamic ordering/filtering.
- File type, size, malware/executable exposure, and path traversal.
- SSRF in crawlers and URL import.
- Prompt injection and data exfiltration.
- Rate limits and denial-of-wallet attacks against OpenAI.
- Webhook forgery and replay.
- Payment/refund privilege.
- Secret and personal-data logging.
- Retention, consent, export, and deletion.
- Audit coverage for privileged actions.

Use framework escaping by default. Sanitize any explicitly allowed rich text. Never render model output as trusted HTML.

---

## UI Implementation Rules

- Match the supplied NeuralDesk screenshots and design direction without hard-coding their sample data.
- Maintain separate visual shells for public, Subscriber, and Super Admin surfaces.
- Use shared tokens for color, spacing, radius, shadows, typography, states, and responsive breakpoints.
- Build reusable components for cards, tables, filters, pagination, badges, forms, modals, drawers, empty states, skeletons, and confirmation dialogs.
- Every asynchronous control needs loading, success, retryable failure, terminal failure, and disabled/permission/quota states.
- Preserve entered form data after validation errors.
- Meet WCAG 2.2 AA: semantic controls, labels, keyboard operation, focus visibility, contrast, reduced-motion consideration, and live-region announcements for chat.
- Do not use color alone to convey status.
- Confirm destructive actions and state whether the operation is reversible.

---

## Super Admin Rules

- Admin navigation includes dashboard, users, plans/subscriptions, contact, notifications, newsletter, support tickets, blog, payments/refunds/webhooks, staff, roles, audit logs, login activity, languages, and system settings.
- All list pages are searchable, paginated, and authorized.
- Sensitive configuration values are encrypted and masked.
- An unchanged masked field must not overwrite the stored secret.
- Role creation/editing must validate known permissions and protect system roles.
- User deletion/suspension must define effects on workspaces, subscriptions, bots, and retained history.
- Admin actions affecting users, access, money, secrets, content publication, queues, or impersonation require audit events.

---

## Testing Requirements

### General

- Use the repository's existing testing framework and conventions.
- Keep tests deterministic; fake OpenAI, vector, mail, storage, broadcasting, and payment providers by default.
- Never call live paid APIs from the normal automated test suite.
- Prefer factories and explicit states over large brittle seed fixtures.
- Assert database state, emitted events/jobs, authorization, and user-visible result.

### Minimum tests per feature

- Happy path.
- Validation failure.
- Unauthenticated response.
- Unauthorized role response.
- Cross-tenant denial.
- Relevant quota/plan denial.
- Provider/job failure and retry behavior.
- Idempotency/concurrency case where relevant.

### Critical regression tests

- Tenant A cannot retrieve Tenant B vectors even with a guessed source/chunk ID.
- Disabled/deleted/superseded knowledge is excluded from new answers.
- Strict grounding falls back below threshold.
- Prompt injection inside a document cannot change system behavior.
- Human takeover prevents subsequent AI auto-replies.
- Private notes never reach visitor APIs, widget broadcasts, or RAG prompts.
- Concurrent bot/source/team creation cannot exceed plan limits.
- Duplicate payment webhooks do not duplicate state or usage.
- Refund totals cannot exceed captured/refundable amount.
- Admin permissions and impersonation restrictions are enforced server-side.

### Commands

Use commands defined by the repository. Typical Laravel commands may include:

```bash
php artisan test
php artisan test --filter=RelevantTest
./vendor/bin/pint --test
```

Do not invent a command as successful. Inspect `composer.json`, `package.json`, and CI configuration first.

---

## Observability and Operations

- Use structured logs with correlation/request/job IDs.
- Include workspace/user/bot/conversation IDs only where safe and useful.
- Scrub prompts, messages, emails, IPs, tokens, keys, and payment data according to policy.
- Record provider latency, error class, model, token usage, and safe request identifiers.
- Emit metrics for queue depth/failure, ingestion duration, retrieval latency/scores, time-to-first-token, answer outcome, handoff, widget availability, webhook status, and payments.
- Add health checks for MySQL, Redis/queues, storage, vector store, real-time server, and critical provider configuration.
- Document worker, scheduler, real-time, backup, restore, deployment, rollback, and key-rotation procedures.

---

## Configuration and Secrets

- Commit `.env.example`, never `.env` or real credentials.
- Access environment values through configuration.
- Validate required production configuration during deployment/health checks.
- Separate OpenAI, vector, mail, storage, broadcasting, and gateway configuration.
- Encrypt database-stored tenant/provider credentials.
- Support key rotation without rewriting unrelated settings.
- Never include secrets in exception messages, job payload displays, frontend state, source maps, or audit diffs.

---

## Documentation Requirements

When adding a major subsystem, update relevant documentation for:

- Architecture and domain boundaries.
- Environment variables.
- Setup/migrations/seeders.
- Queue workers and scheduler.
- Real-time server.
- Vector store and OpenAI configuration.
- Payment webhook endpoints.
- Deployment and rollback.
- Backup/restore and retention.
- Public widget integration and versioning.

Use comments to explain non-obvious decisions and invariants, not obvious syntax.

---

## Definition of Done

A task is complete only when:

- Behavior matches the PRD or an explicitly approved change.
- Tenant scoping and authorization are correct.
- Validation and database constraints are present.
- Loading, empty, error, retry, and destructive states are handled where relevant.
- Side effects are transactional/idempotent as required.
- Security and privacy risks were assessed.
- Relevant tests were added and executed.
- Formatting/static checks pass where configured.
- Configuration and operational changes are documented.
- No unrelated files or behavior were changed.

---

## Decision Escalation

Stop and ask for direction when a missing decision materially changes architecture, security, billing, privacy, or irreversible data behavior. Examples:

- Changing the tenancy model.
- Selecting or replacing the production vector store.
- Defining what an AI credit means.
- Choosing the first production payment gateway/currency.
- Changing retention/deletion semantics.
- Enabling model answers without retrieved evidence.
- Adding a paid or externally hosted dependency.
- Modifying public widget compatibility.
- Performing destructive migrations or permanent data deletion.

For small reversible choices, follow existing repository patterns and document the assumption.

