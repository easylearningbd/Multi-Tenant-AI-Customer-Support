# Product Requirements Document

## Multi-Tenant AI Customer Support SaaS

**Working product name:** NeuralDesk  
**Document status:** Implementation baseline  
**Product type:** Self-hosted, multi-tenant SaaS application  
**Primary stack:** Laravel, Laravel Breeze, MySQL, OpenAI API  
**Core capability:** Retrieval-Augmented Generation (RAG), embedded AI chat, live customer support, lead capture, subscriptions, and platform administration

---

## 1. Executive Summary

NeuralDesk is a professional multi-tenant customer-support SaaS platform. A subscriber creates a workspace, builds one or more AI support bots, trains them on approved business knowledge, and deploys them through an embeddable website widget or a hosted chat page. The platform answers questions using Retrieval-Augmented Generation, monitors visitors, captures leads, stores conversations, allows support agents to collaborate privately, and supports seamless human takeover.

The product has three distinct surfaces:

1. A public marketing website for product discovery, pricing, documentation, and registration.
2. A subscriber workspace for chatbot configuration, training, deployment, conversations, visitors, team operations, billing, and account settings.
3. A separate Super Admin back office for users, subscriptions, payments, plans, platform staff, permissions, content, support, security, and system operations.

The application is self-hostable, but OpenAI remains an external AI provider for embeddings and language-model responses. MySQL is the authoritative transactional database. Semantic retrieval uses a replaceable vector-search adapter, with a self-hosted vector database recommended for production.

---

## 2. Product Vision

Enable businesses to launch accurate, brand-matched AI support in minutes while retaining ownership of their content, customer relationships, support workflow, deployment, and operational data.

### Product principles

- **Evidence before generation:** Answers should be grounded in retrieved tenant knowledge.
- **Tenant isolation by default:** A tenant can never retrieve or modify another tenant's data.
- **Human control:** AI automation must yield cleanly to a human agent.
- **Operational transparency:** Sources, confidence, usage, failures, and handoffs must be inspectable.
- **Professional SaaS behavior:** Quotas, billing, permissions, auditing, retries, and failure states are first-class features.
- **Easy deployment:** A subscriber should be able to deploy a bot with one script tag or a hosted link.
- **Provider portability:** AI, vector search, payments, storage, mail, and real-time services should be replaceable behind interfaces.

---

## 3. Goals and Success Criteria

### 3.1 Business goals

- Convert visitors from the public site into trial or paid workspaces.
- Allow subscribers to create useful support bots without engineering assistance.
- Monetize usage through configurable subscription plans and payment gateways.
- Reduce routine support workload while preserving human escalation.
- Give the platform owner complete operational and financial control.

### 3.2 User goals

- Train a bot from text, files, URLs, sitemaps, and structured questions and answers.
- Receive answers that are traceable to approved knowledge.
- Deploy a widget without rebuilding the customer's website.
- See current visitors and all historical conversations in one inbox.
- Capture visitor details and qualified leads.
- Assign conversations, leave private notes, and take control from the AI.
- Understand plan limits, AI consumption, invoices, and renewal state.

### 3.3 Product success indicators

- Workspace activation rate: registration to first trained and deployed bot.
- Time to first grounded answer.
- RAG answer acceptance and fallback rates.
- Percentage of conversations resolved by AI versus handed to humans.
- Retrieval precision based on reviewed conversations.
- Lead-capture conversion rate.
- Subscriber conversion, renewal, churn, and monthly recurring revenue.
- Payment success, refund, and failure rates.
- Widget availability and median response latency.
- Cross-tenant security incidents: target zero.

---

## 4. Scope

### 4.1 Included

- Public marketing site and pricing.
- Subscriber registration, authentication, password recovery, verification, and optional 2FA.
- Separate Super Admin authentication surface.
- Multi-tenant workspace isolation.
- Bot creation, settings, activation, deletion, and model tuning.
- RAG ingestion, chunking, embedding, indexing, retrieval, citations, confidence, and retraining.
- Text, file, Q&A, URL, and sitemap knowledge sources.
- Embeddable web widget and hosted chat page.
- Visitor identity, session tracking, location metadata, and real-time presence.
- AI and human conversations, assignments, private collaboration, proactive messages, and handoff.
- Configurable pre-chat forms and lead capture.
- Subscriber team membership and access control.
- Subscription plans, limits, metering, checkout, payments, invoices, refunds, and webhooks.
- Super Admin user, plan, subscription, payment, staff, role, content, support, and system management.
- Notifications, audit logs, login activity, localization, and operational monitoring.

### 4.2 Not assumed without a later decision

- Voice or video support.
- Native mobile applications.
- Social-channel inboxes such as WhatsApp, Messenger, or Instagram.
- On-premise LLM inference.
- Automatic actions inside a subscriber's business systems.
- Fine-tuning custom models.
- A specific CRM beyond export, webhook, and configured integrations.

These may be added later without changing the core tenant, conversation, or RAG models.

---

## 5. Personas and Actors

### 5.1 Public visitor

Browses features, pricing, integrations, documentation, FAQs, and marketing content. Can create a workspace or log in.

### 5.2 Subscriber owner

Owns a tenant workspace. Manages bots, knowledge, deployments, team members, billing, workspace settings, and all conversations.

### 5.3 Subscriber support agent

Works inside an assigned workspace. Handles conversations, views visitor context, uses private notes, and takes over from AI according to granted permissions.

### 5.4 Website visitor / lead

Uses an embedded widget or hosted chat page. May provide pre-chat information, ask questions, request a human, and become a captured lead.

### 5.5 Super Admin

Controls the platform, subscribers, plans, subscriptions, payments, refunds, staff, permissions, operational settings, content, and security data.

### 5.6 Platform staff

Uses the Super Admin back office with a limited role. Permissions are explicitly granted per resource and action.

### 5.7 System actors

- Queue workers process ingestion, crawling, embeddings, notifications, and webhooks.
- Schedulers handle recurring jobs and cleanup.
- OpenAI generates embeddings and grounded responses.
- Vector search retrieves semantically related chunks.
- Payment gateways process subscription transactions and refunds.

---

## 6. Authentication, Authorization, and Tenancy

### 6.1 Authentication surfaces

#### Subscriber authentication

- Work-email and password login.
- Remember-me option.
- Password reset.
- Registration through “Create a free workspace.”
- Email verification.
- Optional phone verification.
- Optional two-factor authentication.
- Locale selector.
- Redirect authenticated subscribers to their workspace overview.

#### Super Admin authentication

- Dedicated admin URL, layout, guard, middleware, and post-login destination.
- Email and password login.
- Remember-me and password reset.
- Optional/required 2FA based on platform policy.
- No public admin registration.
- Rate limiting, audit logging, and session controls.

Laravel Breeze supplies the authentication foundation, but authorization and tenant scoping must be implemented separately.

### 6.2 Tenant model

- A workspace is the tenant boundary.
- Every tenant-owned record contains a non-null `workspace_id` unless it is a child whose parent enforces the same boundary.
- Users may belong to one or more workspaces, with one membership per workspace.
- Every request resolves an active workspace through trusted authenticated membership, never from an unverified client field.
- Models, policies, queries, cache keys, events, broadcasts, jobs, files, vector payloads, exports, and logs must preserve the tenant identifier.
- Super Admin access bypasses tenant membership only through explicit privileged policies and must be audited.
- “Login as User” is time-limited impersonation, displays a persistent banner, blocks sensitive actions as configured, and logs start/stop events.

### 6.3 Authorization

- Subscriber roles: owner, admin, agent, viewer, with configurable permissions if required.
- Platform roles: Super Admin and custom staff roles.
- Permissions use explicit resource/action pairs such as `payments.view` and `payments.refund`.
- Server-side policies are authoritative; hidden UI controls are not security boundaries.
- Sensitive actions require recent authentication or confirmation where appropriate.

---

## 7. Core User Journeys

### 7.1 Subscriber onboarding

1. Visitor reviews the home page and plans.
2. Visitor creates a free workspace.
3. System creates the user, workspace, owner membership, and trial subscription atomically.
4. User verifies email and enters the subscriber dashboard.
5. User creates a bot.
6. User adds knowledge and waits for training to complete.
7. User tests the bot, adjusts behavior, and activates it.
8. User copies the embed code or shares the hosted chat link.
9. Conversations, visitors, leads, and usage begin appearing in the workspace.

### 7.2 Grounded AI answer

1. Visitor sends a message.
2. System validates widget, bot, workspace, origin policy, quota, and rate limit.
3. Message is stored before generation begins.
4. Query is normalized and optionally rewritten for retrieval.
5. Vector search retrieves tenant- and bot-authorized chunks.
6. Results are filtered, ranked, deduplicated, and checked against a confidence threshold.
7. System constructs a prompt containing bot behavior, conversation context, retrieved evidence, and response rules.
8. OpenAI streams a response.
9. System stores the answer, citations, model, tokens, cost estimate, retrieval trace, latency, and confidence.
10. If evidence is insufficient or policy requires it, the bot gives the configured fallback and offers human handoff.

### 7.3 Human handoff

1. Visitor asks for a person, retrieval confidence is too low, or a rule triggers escalation.
2. Conversation changes to `needs_human` or `open_manual`.
3. Eligible agents are notified.
4. An agent claims or is assigned the conversation.
5. AI auto-reply is suspended.
6. Agent sees conversation context, captured lead fields, relevant sources, and prior AI activity.
7. Agents may add private notes that are never sent to the visitor.
8. Agent resolves the conversation or returns it to AI according to policy.

### 7.4 Subscription purchase

1. Subscriber compares current usage and available plans.
2. Subscriber selects an active, publicly available plan.
3. Server creates a provider checkout using authoritative plan pricing.
4. Payment provider confirms through a signed webhook.
5. The webhook is processed idempotently.
6. Subscription and entitlements update only after verified provider state.
7. Invoice/payment history appears in the subscriber dashboard.

### 7.5 Super Admin refund

1. Authorized staff opens a payment record.
2. Staff enters a valid full or partial amount and optional reason.
3. Server verifies refundable balance and staff permission.
4. Gateway refund is submitted with an idempotency key.
5. Payment/refund state updates from the verified result or webhook.
6. The action is recorded in the audit log.

---

## 8. Functional Requirements — Public Website

### 8.1 Header and navigation

- Brand identity and logo.
- Product, integrations, pricing, resources, and blog navigation.
- Subscriber login and primary start-free CTA.
- Responsive mobile navigation.

### 8.2 Home page sections

- Hero focused on training AI chatbots quickly.
- Product visual showing an AI assistant conversation.
- Customer proof or trust indicators.
- How-it-works sequence: Upload, Train, Deploy.
- Feature presentation: source-backed answers, smart handoff, framework checks, tone/guardrails, and knowledge gaps.
- Integrations presentation, including the illustrated examples Notion, Slack, Zendesk, Intercom, Google Drive, and HubSpot.
- Customer testimonials.
- Pricing cards for Starter, Growth, and Enterprise/custom plans.
- FAQ accordion.
- Final CTA section.
- Footer with product, company, resource, legal, newsletter, and social links.

### 8.3 Public content management

Super Admin-manageable areas include:

- Pages and sections.
- Navigation menus.
- Theme/home-page settings.
- Blog posts and categories.
- Media.
- Supported languages.
- Contact submissions.
- Newsletter subscriptions and campaigns.

---

## 9. Functional Requirements — Subscriber Workspace

### 9.1 Workspace shell

- Persistent navigation: Overview, Live Visitors, Bots, Conversations, Team, Billing, Settings.
- Workspace identity and current plan.
- Notification center.
- Responsive sidebar and accessible keyboard navigation.
- All counts and metrics scoped to the active workspace.

### 9.2 Overview

- Conversation totals with open and resolved counts.
- AI-resolution rate and count.
- Active bots against plan capacity.
- Knowledge-base/source/chunk coverage.
- AI answers per day for a selectable period.
- Recent conversations.
- Knowledge-gap summary, including low-confidence answers and missing sources.
- Links to manage bots and inspect relevant details.

### 9.3 Live visitors

- Show active sessions and recent sessions from the last 24 hours.
- Update presence without a full page refresh.
- Display anonymous visitor identifier, message count, approximate location, IP according to privacy settings, bot, referring/trigger URL, host domain, and last-seen time.
- Show online and daily totals.
- Open or initiate a conversation when permissions and plan allow.
- Support proactive agent messages with abuse controls and origin/session validation.

### 9.4 Bots

#### Bot list

- Display bot name, slug/identifier, live status, source count, widget count, and active state.
- Actions: settings, training, embed/share, and create bot.
- Enforce plan capacity transactionally.

#### Create bot

- Required bot name.
- Generate a unique workspace-scoped slug/identifier.
- Create in a safe draft/inactive state.
- Route user to setup/training after creation.

#### Bot settings

- Identity: internal bot name and public display name.
- Assigned integrations.
- Welcome message.
- Up to the configured number of starter questions; illustrated design shows six maximum and three active.
- Configurable pre-chat form.
- Built-in fields: name, email, phone.
- Custom fields with label, type, required flag, order, and validation.
- Tone and primary language.
- Fallback message.
- Human-handoff toggle and rules.
- “Answer only from knowledge base” guardrail.
- Persona/system behavior instructions.
- Assigned knowledge bases.
- Optional model override constrained to administrator-approved models.
- Temperature, max tokens, and knowledge-confidence threshold.
- Active/inactive state.
- Live preview.
- Delete bot danger zone with confirmation and defined retention behavior.

### 9.5 Bot training and knowledge

- Add direct knowledge text.
- Upload PDF, DOCX, Markdown, TXT, and CSV files; initial illustrated limit is 20 MB per file and must be configurable.
- Add structured Q&A entries.
- Crawl a page, URL collection, or sitemap with configurable limits.
- Re-sync a website source.
- List sources with type, chunk count, status, updated time, and last-trained time.
- Edit, retrain, disable, or delete a source.
- Retrain the bot from current active sources.
- Expose source lifecycle states: draft, queued, processing, trained, failed, stale, disabled.
- Show useful failure details without exposing secrets.
- Deleting/disabling a source must remove or exclude its chunks and vectors.
- Training must be asynchronous, resumable, idempotent, and observable.

### 9.6 Embed and share

- Generate a per-widget loader script suitable for placement before `</body>`.
- Support WordPress, Shopify, React, and plain HTML through the same stable public loader contract.
- Copy-to-clipboard action.
- Hosted chat URL requiring no website changes.
- Demo-site link.
- Widget appearance: approved accent colors or configurable color picker.
- Widget position: bottom-left or bottom-right.
- Configurable welcome message.
- Live preview before save.
- Widget status indicator.
- Domain allowlist and optional signed visitor identity.
- Versioned loader with backward compatibility.

### 9.7 Hosted chat page

- Public bot identity and online/availability state.
- Welcome message and starter questions.
- Conversation composer and streaming responses.
- Subscriber branding and optional platform branding based on plan.
- Accessible, responsive layout.
- Same RAG, quota, moderation, persistence, and handoff pipeline as embedded chat.

### 9.8 Conversations inbox

- Search conversation content and visitor data subject to permissions.
- Filters: all, open, needs-human/manual, assigned, unassigned, resolved, archived, bot, agent, date, and lead status.
- Conversation list with visitor identifier/name, preview, time, status, and urgency/handoff badges.
- Thread view distinguishing visitor, AI, and human messages.
- Conversation states: open AI, needs human, manual, resolved, archived/spam.
- Enable/disable AI auto-reply.
- Assign, claim, transfer, resolve, reopen, archive, and delete according to policy.
- Internal notes and agent mentions invisible to visitors.
- Attachments with safe validation and access control.
- Composer state must explain when AI owns the conversation.
- Store delivery and read state when supported.
- Preserve an immutable activity timeline for operational changes.

### 9.9 Leads

- Convert pre-chat submissions and qualified conversations into leads.
- Store workspace, bot, visitor, conversation, name, email, phone, custom fields, consent, source URL, campaign/referrer data, qualification state, owner, tags, and timestamps.
- Allow search, filtering, assignment, notes, export, and deletion/retention actions.
- Support lead qualification rules without allowing generated text to silently overwrite verified visitor data.
- Treat visitor contact information as sensitive tenant data.

### 9.10 Team

- Invite agents by email.
- Accept, resend, or revoke invitations.
- Assign workspace roles and permissions.
- Activate/deactivate members without deleting historical attribution.
- Display presence and conversation workload when enabled.
- Prevent removal of the last workspace owner.
- Enforce plan member limits.

### 9.11 Billing

- Display current plan, active/trial/past-due/canceled state, and renewal date.
- Display consumption against entitlements: AI answers/credits, bots, knowledge bases, sources, team members, and storage.
- Show available active plans and feature bullets.
- Support fixed monthly/yearly plans and custom/contact-sales plans.
- Checkout, upgrade, downgrade, cancel, and resume according to gateway capability.
- Display invoice/payment history with amount, date, description, gateway, and status.
- Prevent client-side price or entitlement manipulation.
- Define clear behavior when limits are exceeded or a subscription lapses.

### 9.12 Subscriber settings

- Profile name, email, phone, avatar where enabled.
- Email and phone verification status.
- Password change.
- 2FA setup and recovery.
- Notification preferences.
- Support tickets.
- Session/device management.
- Workspace settings, data export, and account/workspace deletion with safeguards.

---

## 10. Functional Requirements — RAG and OpenAI

### 10.1 Ingestion pipeline

1. Validate tenant entitlement, source type, size, MIME type, origin, and ownership.
2. Store the original source using a tenant-scoped path and immutable identifier.
3. Extract content in a sandboxed/background process.
4. Normalize encoding, whitespace, headings, tables, and metadata.
5. Reject or quarantine unsupported/encrypted/malicious content.
6. Split content into semantically useful chunks with controlled overlap.
7. Retain source location metadata such as page, heading, row, or URL.
8. Generate embeddings using an administrator-approved OpenAI embedding model.
9. Upsert vectors with workspace, bot/knowledge-base, source, chunk, model, and version metadata.
10. Mark the source trained only when the intended index version is complete.

### 10.2 Retrieval pipeline

- Always filter retrieval by `workspace_id` and authorized bot/knowledge bases before ranking.
- Support vector similarity and optional hybrid keyword retrieval.
- Apply configurable top-K retrieval, minimum score, deduplication, diversity, and optional reranking.
- Exclude disabled, deleted, stale, or superseded chunks.
- Respect the source version used for the answer.
- Treat retrieved source content as untrusted data, not system instructions.
- Log retrieval metadata for evaluation without exposing private content across tenants.

### 10.3 Context augmentation

The prompt package should include:

- Platform safety and grounding rules.
- Subscriber-configured bot persona, tone, language, and fallback policy.
- Relevant conversation history within a controlled token budget.
- Retrieved evidence with stable source identifiers.
- The current user question.
- Explicit instruction to ignore commands embedded inside retrieved documents.
- Required output and citation format.

### 10.4 Generation

- Use the OpenAI API through an internal provider interface.
- Keep API keys server-side and encrypted where stored.
- Stream responses when supported.
- Enforce configured model allowlist, temperature, token limit, timeout, and retry policy.
- Do not retry non-idempotent generation blindly after an uncertain streamed result.
- Persist provider request ID when available, model, token counts, latency, finish reason, and estimated cost.
- Moderate input/output according to platform policy.
- Never reveal system prompts, credentials, internal retrieval traces, or private notes.

### 10.5 Grounding and fallback

- When “answer only from knowledge base” is enabled, unsupported claims are forbidden.
- Low-confidence retrieval triggers the configured fallback rather than model improvisation.
- Fallback may offer human handoff.
- Citations link an answer to exact stored source/chunk versions.
- A source deletion policy determines whether historical citations remain as tombstoned metadata or are removed for privacy.

### 10.6 RAG evaluation

- Maintain a test-question set per bot or workspace.
- Track retrieval relevance, groundedness, citation correctness, answer completeness, fallback correctness, and latency.
- Allow subscribers to mark answers helpful/unhelpful and flag incorrect answers.
- Turn unresolved or low-confidence questions into knowledge gaps.
- Never auto-promote unreviewed model responses into trusted knowledge.

### 10.7 Vector-search architecture

- MySQL remains the system of record for sources, chunks, permissions, and version metadata.
- Semantic vectors live behind a `VectorStore` interface.
- Recommended production default: self-hosted Qdrant.
- Development/test adapter may use a deterministic fake or local implementation.
- No business logic may depend directly on vendor-specific vector APIs.
- Every vector payload must contain sufficient tenant and source filters.

---

## 11. Functional Requirements — Super Admin

### 11.1 Dashboard

- Net revenue, total payments, pending payments, payment issues, and refunded amount.
- Revenue timeline showing collected revenue and refunds.
- Payment-health summary with completion, failure, pending, and refund metrics.
- Gateway mix.
- Recent payments, users, and login activity.
- Support-ticket summary by status/urgency.
- Date and currency handling must be consistent and configurable.

### 11.2 Users

- Searchable, sortable, paginated user list.
- Create, view, edit, activate/suspend, soft-delete, restore, and permanently delete according to retention policy.
- User detail: identity, verification, 2FA, roles, last login, IP, creation/update timestamps, timezone, and deletion state.
- Inspect workspace memberships, subscriptions, usage, payments, and security activity.
- Audited “Login as User” capability.
- Never expose password hashes, reset tokens, 2FA secrets, or API secrets.

### 11.3 Plans and subscriptions

- Create/edit plan name, slug, description, features, price, interval, sort order, and status.
- Support free trial, fixed-price, and custom/contact-sales plans.
- Configure AI credits/answers, chatbots, knowledge bases, knowledge sources, storage, team members, and other entitlements.
- Use zero as unlimited only where the UI and domain service explicitly define it.
- Existing subscriptions retain a plan snapshot so later plan edits do not rewrite history.
- View and manage subscriptions, states, provider identifiers, periods, cancellation, and overrides.
- Destructive plan changes must be blocked when unsafe or require migration behavior.

### 11.4 Payments, refunds, and webhooks

- Searchable, sortable, paginated payments list.
- Payment detail includes UUID, amount/currency, status, gateway, gateway ID, customer, method, timestamps, description, subscription, and sanitized metadata.
- Supported adapter examples: Stripe, PayPal, Razorpay, and manual payment.
- Full and partial refunds with reason, validation, idempotency, authorization, and audit records.
- Separate refund list.
- Webhook log with provider, event ID/type, verification state, attempts, result, timestamps, and safe redacted payload.
- Replay only through controlled, idempotent processing.

### 11.5 Platform staff and roles

- Create/edit staff name, email, phone, password/invitation, role assignments, and active state.
- List/search staff and preserve historical attribution when deactivated.
- Create/edit/delete roles while protecting required system roles.
- Granular permissions cover:
  - Staff and roles.
  - Notification templates/logs and system notifications.
  - AI settings.
  - Blog posts and categories.
  - Currencies and languages.
  - Support tickets.
  - Payments, refunds, and webhook logs.
  - Plans and subscriptions.
  - Newsletter.
  - Audit and login activity.
  - General and payment-gateway settings.
  - Scheduler/queues.
  - Media.
  - Frontend themes, menus, sections, and pages.
  - Contact submissions and home-page settings.
  - Users and dashboard.

### 11.6 Platform content and communication

- Contact-submission management and replies.
- Notification templates and delivery logs.
- In-app/system notification composition.
- Newsletter subscribers, campaigns, sending, and opt-out compliance.
- Support tickets with assignment, status, priority, and replies.
- Blog posts/categories and publishing workflow.
- Frontend pages, menus, sections, themes, home-page settings, and media.

### 11.7 System operations

- AI provider settings and approved model list.
- Payment gateway credentials and enablement.
- Currency and locale settings.
- Queue and scheduled-job visibility/control appropriate to permission.
- Audit logs and login activity.
- Maintenance, retention, mail, storage, security, widget, and legal settings.
- Secrets must be encrypted and masked; saving an unchanged masked value must not overwrite the real secret.

### 11.8 Admin profile

- Name, email, phone, and avatar.
- Password change.
- Enable/disable 2FA with confirmation and recovery codes.
- View and revoke active sessions.

---

## 12. Plans, Entitlements, and Metering

### 12.1 Illustrated initial catalog

| Plan | Price | Illustrative limits/features |
| --- | ---: | --- |
| Free Trial | $0 trial | 1 bot, 1 knowledge base, 25 sources, 1,000 AI answers |
| Starter | $29/month | 1 bot, 1 knowledge base, 25 sources, 1,000 AI answers, document/URL training, citations |
| Growth | $99/month | 5 bots, 5 knowledge bases, 100 sources, 10,000 AI answers, integrations, analytics, knowledge gaps, handoff rules |
| Enterprise | Custom | Custom/unlimited entitlements, advanced security, audit capabilities, private deployment options, priority support |

All values are administrator-configurable. The implementation must not hard-code the illustrated catalog.

### 12.2 Metering rules

- Define the billable unit precisely: generated AI answer, OpenAI token/credit, or configurable credit calculation.
- Reserve/check entitlement before an expensive provider call and finalize usage after completion.
- Avoid double counting through idempotency keys.
- Record usage ledger entries with workspace, bot, conversation/message, type, quantity, model, period, and source event.
- Aggregate ledgers into fast dashboard counters without losing auditability.
- Apply warnings and hard limits consistently across UI, API, widget, and queue jobs.

---

## 13. Suggested Domain Model

### 13.1 Identity and tenancy

- `users`
- `workspaces`
- `workspace_memberships`
- `workspace_invitations`
- `roles`, `permissions`, role/permission pivots
- `user_sessions`, `login_activities`
- `impersonation_sessions`

### 13.2 Bots and knowledge

- `bots`
- `bot_starter_questions`
- `bot_prechat_fields`
- `knowledge_bases`
- `bot_knowledge_base`
- `knowledge_sources`
- `source_versions`
- `knowledge_chunks`
- `training_runs`
- `retrieval_runs`
- `rag_evaluations`
- `knowledge_gaps`

### 13.3 Widget, visitors, conversations, and leads

- `widgets`
- `widget_domains`
- `visitor_profiles`
- `visitor_sessions`
- `conversations`
- `conversation_participants`
- `messages`
- `message_citations`
- `conversation_assignments`
- `conversation_notes`
- `conversation_events`
- `leads`
- `lead_field_values`
- `tags` and tag pivots

### 13.4 Billing

- `plans`
- `plan_features` or entitlement definitions
- `subscriptions`
- `subscription_items`/snapshots
- `usage_ledgers`
- `usage_periods`
- `payments`
- `refunds`
- `invoices`
- `payment_webhook_events`
- `gateway_customers`

### 13.5 Platform operations

- `support_tickets`, `support_ticket_replies`
- `contact_submissions`
- `notifications`, `notification_templates`, `notification_logs`
- `newsletter_subscribers`, `newsletter_campaigns`
- `blog_posts`, `blog_categories`
- `pages`, `page_sections`, `menus`, `media`
- `languages`, `currencies`
- `settings`
- `audit_logs`
- `job/queue monitoring records` where needed

### 13.6 Data conventions

- Use ULIDs/UUIDs for public identifiers; do not expose predictable internal IDs.
- Money uses integer minor units plus ISO currency, never floating-point.
- Store timestamps in UTC and render in the user's timezone.
- Use explicit status enums/value objects and documented transitions.
- Use soft deletion only where recovery and retention semantics are defined.
- Add foreign keys, unique constraints, and compound tenant indexes.
- Preserve immutable snapshots for billing and cited source versions.

---

## 14. Service Architecture

### 14.1 Application components

- Laravel web application and JSON endpoints.
- Breeze-based authentication.
- MySQL transactional database.
- Redis for cache, locks, rate limiting, queues, and presence where deployed.
- Laravel queue workers and scheduler.
- Laravel Reverb or an equivalent abstraction for real-time events.
- Object storage through Laravel Filesystem, supporting local and S3-compatible targets.
- Vector store through an internal adapter; Qdrant recommended for production.
- OpenAI provider adapter for embeddings and chat responses.
- Payment gateway adapters.
- Mail and notification channels.
- Health checks, structured logs, metrics, tracing/error reporting, and backups.

### 14.2 Key application services

- `TenantContext`
- `BotConfigurationService`
- `KnowledgeIngestionService`
- `DocumentExtractor`
- `ChunkingStrategy`
- `EmbeddingProvider`
- `VectorStore`
- `Retriever` and optional `Reranker`
- `PromptBuilder`
- `ChatCompletionProvider`
- `ConversationOrchestrator`
- `HandoffService`
- `LeadCaptureService`
- `EntitlementService`
- `UsageMeter`
- `SubscriptionService`
- `PaymentGateway`
- `WebhookProcessor`
- `AuditLogger`

### 14.3 Background jobs

- Extract source content.
- Crawl/sync website.
- Chunk and embed source version.
- Delete/synchronize vectors.
- Retrain/reindex a bot.
- Generate asynchronous exports.
- Send invitations and notifications.
- Process webhook events.
- Reconcile subscriptions/payments.
- Aggregate usage analytics.
- Detect stale visitors and close presence sessions.
- Apply retention and deletion workflows.

Every job must carry a tenant identifier where relevant, be idempotent, define retry/backoff behavior, and surface terminal failure.

---

## 15. API and Event Boundaries

### 15.1 Subscriber routes

- Auth and verification routes.
- Workspace overview and analytics.
- Bot CRUD, settings, training, sources, preview, and deployment.
- Live visitor presence and proactive-chat actions.
- Conversation list/detail, messages, assignment, notes, handoff, and resolution.
- Leads and exports.
- Team invitations and membership.
- Billing, checkout, invoices, and subscription actions.
- Profile, security, notification, and support settings.

### 15.2 Public widget routes

- Versioned loader asset.
- Widget configuration bootstrap.
- Visitor/session bootstrap.
- Pre-chat submission.
- Send message and consume streamed response.
- Request human handoff.
- Presence heartbeat.
- Attachment upload if enabled.

Public endpoints require bot/widget validation, allowed-origin checks, abuse prevention, privacy-aware session tokens, and tenant resolution on the server.

### 15.3 Admin routes

- Admin authentication and profile/security.
- Dashboard analytics.
- Users and impersonation.
- Plans, subscriptions, entitlements, and overrides.
- Payments, refunds, and webhook logs.
- Staff, roles, and permissions.
- Content, contact, newsletter, support, notifications, languages, and currencies.
- AI, gateway, queue, frontend, and system settings.
- Audit and login activity.

### 15.4 Events

Representative events include:

- `WorkspaceCreated`
- `BotCreated`, `BotActivated`
- `KnowledgeSourceQueued`, `KnowledgeSourceTrained`, `KnowledgeSourceFailed`
- `VisitorCameOnline`, `VisitorWentOffline`
- `ConversationStarted`, `MessageCreated`
- `HumanHandoffRequested`, `ConversationAssigned`, `ConversationResolved`
- `LeadCaptured`, `LeadQualified`
- `SubscriptionActivated`, `SubscriptionChanged`, `SubscriptionCanceled`
- `PaymentCompleted`, `PaymentFailed`, `RefundCompleted`

Broadcast and listener authorization must retain workspace scope.

---

## 16. Security, Privacy, and Abuse Prevention

- Follow OWASP application and API security practices.
- Enforce CSRF protection for browser sessions and appropriate token controls for public widget sessions.
- Validate and authorize every request server-side.
- Use strict tenant scoping and automated isolation tests.
- Encrypt sensitive credentials at rest; use environment or secret-manager configuration for platform keys.
- Redact secrets, message content where required, and personal data from logs.
- Validate uploads by content and MIME; randomize storage names; prevent executable access.
- Harden crawlers against SSRF, private networks, redirects, oversized responses, and unsafe protocols.
- Verify webhook signatures and deduplicate provider event IDs.
- Rate-limit login, registration, password reset, widget bootstrap, messages, crawls, training, uploads, and proactive chat.
- Protect against prompt injection by separating instructions from retrieved content and treating sources as untrusted.
- Prevent source citations from creating unauthorized file access.
- Provide consent and configurable retention for IP/location, transcripts, and lead data.
- Support account/workspace export and deletion workflows.
- Record privileged actions in append-oriented audit logs.
- Back up MySQL, object storage metadata, and vector indexes; document restoration tests.

---

## 17. Non-Functional Requirements

### 17.1 Performance

- Standard authenticated page/API requests should target a p95 below 500 ms excluding external providers and large reports.
- Widget bootstrap should target a p95 below 800 ms under normal production load.
- Streaming should begin as soon as practical after retrieval, with a target time-to-first-token below 3 seconds under normal provider conditions.
- Long-running ingestion, crawling, embeddings, exports, and webhook reconciliation run asynchronously.

### 17.2 Reliability

- No message should be lost if the AI provider fails after user submission.
- Use transactions for multi-record state changes.
- Use locks/idempotency for quota reservations, webhook processing, refunds, and retraining.
- Display degraded states and retry-safe user actions.
- Define health checks for database, cache/queue, vector store, storage, real-time server, and configured external providers.

### 17.3 Scalability

- Stateless web nodes where possible.
- Horizontally scalable queue workers separated by workload.
- Vector and file storage must scale independently of MySQL.
- Cache expensive dashboards while invalidating by tenant/event.
- Avoid N+1 queries and unbounded exports or list endpoints.

### 17.4 Accessibility and responsiveness

- Target WCAG 2.2 AA for public, subscriber, admin, hosted-chat, and widget surfaces.
- Full keyboard support, visible focus, semantic labels, sufficient contrast, and screen-reader announcements for live chat.
- Responsive behavior for mobile, tablet, laptop, and wide desktop.

### 17.5 Localization

- Translation-ready UI strings.
- User/workspace locale preferences.
- UTC persistence and localized date/time rendering.
- ISO currencies and gateway-compatible formatting.
- Bot primary language and multilingual response rules.

### 17.6 Observability

- Structured logs with request/job correlation IDs and tenant IDs where safe.
- Metrics for HTTP, queues, provider latency/errors, retrieval scores, messages, token usage, cost, payments, and webhooks.
- Error reporting with sensitive-data scrubbing.
- Admin-operational visibility without exposing raw secrets.

---

## 18. UI and Design Requirements

- Preserve the supplied NeuralDesk visual direction: bright, spacious SaaS layouts; white/light-gray surfaces; dark navy actions; blue/violet/cyan gradients; rounded cards; restrained shadows; readable typography.
- Subscriber login uses a split layout with a dark product/chat illustration panel and a light authentication panel.
- Admin login is visually distinct and emphasizes secure operational access.
- Subscriber and admin dashboards must remain visually and navigationally distinct.
- Use reusable design tokens and components rather than page-specific CSS duplication.
- All forms require clear validation, loading, success, empty, permission-denied, quota, and failure states.
- Destructive actions require explicit confirmation and clear consequences.
- Screenshots are visual references, not permission to hard-code sample names, counts, dates, prices, or statuses.

---

## 19. Testing Strategy

### 19.1 Required test layers

- Unit tests for domain rules, status transitions, chunking, prompt construction, metering, and money calculations.
- Feature tests for authentication, authorization, tenant scoping, CRUD, billing, widget sessions, and admin workflows.
- Integration/contract tests for OpenAI, vector store, storage, email, real-time broadcasting, and payment gateways using fakes/sandboxes.
- Queue-job tests for idempotency, retries, stale versions, and terminal failures.
- Browser/end-to-end tests for critical journeys.
- Security tests for IDOR, cross-tenant access, origin enforcement, uploads, SSRF, prompt injection, webhook replay, and role escalation.
- RAG evaluation tests using fixed knowledge and expected citations.

### 19.2 Critical acceptance suites

- Tenant A cannot retrieve Tenant B's source, vector, message, lead, invoice, or broadcast event.
- A disabled/deleted source stops influencing new answers.
- Low-confidence retrieval produces fallback/handoff, not unsupported claims.
- AI stops responding after a human takes over.
- An agent's private note never appears in the visitor channel.
- Plan limits cannot be bypassed through direct HTTP requests or concurrent operations.
- Duplicate webhook delivery does not duplicate payments, subscriptions, or refunds.
- Admin permission restrictions are enforced server-side.
- Impersonation is visible, reversible, limited, and audited.

---

## 20. Delivery Phases

### Phase 0 — Foundation

- Confirm supported Laravel/PHP versions and infrastructure.
- Establish design system, environments, CI, coding standards, and test baseline.
- Implement Breeze auth, separate admin auth, tenancy, memberships, policies, roles, auditing, and secure settings.

### Phase 1 — Subscriber core

- Workspace overview shell.
- Bot CRUD and settings.
- Knowledge bases and source ingestion.
- RAG pipeline and test console.
- Hosted chat page.

### Phase 2 — Widget and conversations

- Versioned embed loader and widget.
- Visitor sessions/presence.
- Conversation inbox and streaming.
- Human takeover, assignments, internal notes, proactive chat, and notifications.
- Pre-chat forms and leads.

### Phase 3 — SaaS billing

- Admin-configurable plans and entitlements.
- Usage ledger and enforcement.
- Gateway adapters, checkout, subscriptions, payments, invoices, webhooks, refunds, and billing UI.

### Phase 4 — Super Admin operations

- Dashboard analytics.
- Users, impersonation, subscriptions, payments, refunds, staff, and permissions.
- Support, notifications, contact, newsletter, blog/content, localization, system settings, audit, and operations.

### Phase 5 — Production hardening

- RAG evaluation and quality dashboards.
- Performance/load testing.
- Accessibility review.
- Security assessment.
- Backups and disaster-recovery rehearsal.
- Deployment, monitoring, runbooks, data retention, and launch checklist.

---

## 21. Release Acceptance Criteria

The initial production release is acceptable only when:

- Subscriber and Super Admin authentication are separate and secure.
- Tenant isolation is covered by automated tests across all tenant-owned domains.
- A subscriber can register, create/train a bot, test it, deploy it, and receive grounded answers.
- File/text/Q&A/URL knowledge sources complete an observable asynchronous training lifecycle.
- Answers retain citations and retrieval metadata, and unsupported questions follow fallback policy.
- Widget and hosted chat use the same authoritative conversation/RAG pipeline.
- Visitors, leads, conversations, assignments, private notes, handoff, and resolution work end to end.
- Plans and limits are configurable and enforced server-side.
- At least one production payment gateway completes checkout, webhook activation, invoice history, cancellation, and refund flows safely.
- Super Admin can manage users, plans, payments, staff, roles, and critical system settings.
- Logs and dashboards expose operational failures without leaking secrets.
- Critical unit, feature, integration, browser, RAG, and security suites pass.
- Production deployment, worker, scheduler, real-time, storage, backup, and rollback instructions exist.

---

## 22. Open Decisions

These must be decided before the affected implementation begins:

- Exact Laravel/PHP versions and frontend stack.
- Single-database tenancy versus database-per-tenant; this PRD assumes shared MySQL with strict row scoping.
- Production vector store; Qdrant is the recommended self-hosted default.
- Redis and Laravel Reverb deployment choices.
- OpenAI chat and embedding model allowlists, budgets, and regional/privacy requirements.
- Exact definition of an AI credit/answer.
- Trial duration and behavior after expiration.
- Initial payment gateway and merchant currency.
- Subscriber team roles and permission granularity.
- Data retention, deletion, transcript export, and IP-geolocation policy.
- Attachment support and maximum sizes.
- Supported crawler depth, domain rules, scheduling, and robots-policy behavior.
- Branding removal and custom-domain entitlements.
- Whether secondary admin content modules ship in the first release or a later milestone.

---

## 23. Glossary

- **Workspace:** Tenant boundary owned by a subscriber.
- **Bot:** Configured AI assistant deployed through a widget or hosted page.
- **Knowledge base:** Approved collection of sources assigned to bots.
- **Source:** Uploaded file, text, Q&A entry, page, or crawled site input.
- **Chunk:** Searchable portion of a processed source.
- **Embedding:** Vector representation generated for semantic comparison.
- **RAG:** Retrieval-Augmented Generation; retrieval of approved evidence before LLM generation.
- **Handoff:** Transfer of a conversation from AI automation to a human agent.
- **Lead:** Captured and potentially qualified visitor/contact record.
- **Entitlement:** Plan-defined feature or usage allowance.
- **Usage ledger:** Auditable record of billable or limited consumption.
- **Platform staff:** Authorized operator of the Super Admin back office.

