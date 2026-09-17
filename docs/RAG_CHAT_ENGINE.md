# RAG chat engine

Phase 5 adds the authenticated, tenant-scoped RAG engine used by later widget and hosted-chat work. Public widget credentials and unauthenticated chat routes remain Phase 7 concerns.

## Request flow

`POST /bots/{bot-public-id}/rag/messages` accepts a message, a client-generated UUID idempotency key, and an optional conversation UUID. The subscriber and bot are resolved from the authenticated account; no tenant identifier is accepted. The request persists the visitor message, reserves one AI-answer entitlement, and dispatches `GenerateConversationReply` after the database transaction commits. The response is HTTP 202 with an authenticated polling URL.

`GET /bots/{bot-public-id}/rag/conversations/{conversation-uuid}` returns a paginated safe transcript. Provider request IDs, internal errors, embeddings, retrieval scores, prompts, and usage event keys are never serialized.

The queue job embeds the query, searches active MySQL chunks after strict user and bot filtering, applies the bot confidence threshold, records a content-free retrieval trace, builds a guarded prompt, and calls the OpenAI Responses API. Provider storage is disabled with `store: false`. A stable idempotency key protects provider retries.

## Grounding and handoff

Retrieved documents are wrapped as untrusted evidence. The system instruction explicitly rejects commands, role changes, and secret requests contained in that evidence. Source citations use stable knowledge-source UUIDs.

When `answer_only_from_knowledge_base` is enabled and no chunk meets the configured confidence threshold, the configured fallback is persisted without calling the chat model. If human handoff is enabled, the conversation transitions to `needs_human`. A conversation in manual control cannot receive a queued AI reply; the job rechecks its state under a row lock immediately before persistence.

## Usage and idempotency

One successfully persisted provider-generated answer consumes one `ai_answers_per_month` unit. A reservation is created in the same transaction as the visitor message, so concurrent requests cannot bypass a finite limit. Strict-grounding fallbacks, stopped jobs, and terminal provider failures release the reservation. Zero continues to mean unlimited through the existing plan-limit domain service.

Message idempotency is unique within the subscriber and bot. Each visitor message can have only one reply. Jobs contain IDs only, re-query every model with subscriber and bot filters, and implement queue uniqueness. Optional cost estimates use integer minor units from the server-controlled `OPENAI_MODEL_PRICING_JSON`; no pricing is guessed when a model is absent.

## Operations

Run the AI response worker together with the Phase 4 ingestion queues:

```bash
php artisan queue:work --queue=ai-responses,knowledge-ingestion,embeddings,notifications,default --tries=3
```

Required AI settings remain server-side. Phase 5 additionally supports:

```env
OPENAI_CHAT_MAX_RETRIES=2
OPENAI_MODEL_PRICING_JSON={}
RAG_RETRIEVAL_TOP_K=5
RAG_MESSAGE_MAX_LENGTH=4000
RAG_HISTORY_MESSAGE_LIMIT=10
RAG_HISTORY_CHARACTER_LIMIT=12000
RAG_EVIDENCE_CHARACTER_LIMIT=16000
RAG_REQUESTS_PER_MINUTE=12
```

The model pricing JSON shape is a map keyed by approved model name. Values contain `input_per_million_minor`, `output_per_million_minor`, and a three-letter `currency`. It is optional and should be maintained by operators as provider pricing changes.

MySQL remains the only vector backend. Similarity is calculated in PHP after indexed subscriber and bot filtering and lazy database iteration, as documented in `KNOWLEDGE_TRAINING.md`.
