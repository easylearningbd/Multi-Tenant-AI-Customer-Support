# RAG chat engine

Phase 5 adds the tenant-scoped RAG engine. Phase 7 routes public widget and hosted-chat messages into this same queue, usage reservation, retrieval, conversational generation, citation metadata, and handoff pipeline through scoped visitor sessions.

## Request flow

`POST /bots/{bot-public-id}/rag/messages` accepts a message, a client-generated UUID idempotency key, and an optional conversation UUID. The subscriber and bot are resolved from the authenticated account; no tenant identifier is accepted. The request persists the visitor message, reserves one AI-answer entitlement, and dispatches `GenerateConversationReply` after the database transaction commits. The response is HTTP 202 with an authenticated polling URL.

`GET /bots/{bot-public-id}/rag/conversations/{conversation-uuid}` returns a paginated safe transcript. Provider request IDs, internal errors, embeddings, retrieval scores, prompts, and usage event keys are never serialized.

The queue job first uses the configured query-rewrite model to turn the latest visitor message into a standalone retrieval question using at most the previous five visitor/assistant messages. If rewriting fails safely, retrieval uses the original message. The rewritten query is embedded with the same configured embedding model used during knowledge ingestion. MySQL search then selects the five best active chunks after strict user, bot, source-status, embedding-model, and dimension filtering. It applies no minimum similarity cutoff. The job records a content-free retrieval trace, builds a guarded prompt with the previous five messages, and always calls the OpenAI Responses API for generation. Provider storage is disabled with `store: false`; stable per-message idempotency keys protect rewrite and generation retries.

The legacy per-bot confidence value remains stored for configuration compatibility and diagnostics, but it no longer prevents retrieval or generation. Changing the embedding model requires retraining existing knowledge because query search deliberately excludes stored vectors created by a different model or dimension count.

## Grounding and handoff

Retrieved documents are wrapped as untrusted CONTEXT. The system instruction explicitly rejects commands, role changes, and secret requests contained in that content. The model is told never to emit citation markers or mention context, sources, chunks, training data, retrieval, or the knowledge base. The existing response sanitizer remains a second boundary that removes legacy `[source:...]` markers before persistence and serialization. Structured rows record which top-five chunks were supplied for internal diagnostics without exposing scores to visitors.

The model must use CONTEXT for factual claims. When it does not contain the requested information, the model still responds naturally, explains the limitation warmly, and offers to connect the visitor with a person. There is no similarity-based canned fallback. When human handoff is enabled, the visitor can explicitly request it through the public handoff action, which transitions the conversation to `needs_human`. A conversation in manual control cannot receive a queued AI reply; the job rechecks its state under a row lock immediately before persistence.

## Usage and idempotency

One successfully persisted provider-generated answer consumes one `ai_answers_per_month` unit. A reservation is created in the same transaction as the visitor message, so concurrent requests cannot bypass a finite limit. Query-rewrite and answer token usage are combined in the operation ledger. Stopped jobs and terminal provider failures release the reservation. Zero continues to mean unlimited through the existing plan-limit domain service.

Message idempotency is unique within the subscriber and bot. Each visitor message can have only one reply. Jobs contain IDs only, re-query every model with subscriber and bot filters, and implement queue uniqueness. Optional cost estimates use integer minor units from the server-controlled `OPENAI_MODEL_PRICING_JSON`; no pricing is guessed when a model is absent.

## Operations

Run the AI response worker together with the Phase 4 ingestion queues:

```bash
php artisan queue:work --queue=ai-responses,knowledge-ingestion,embeddings,notifications,default --tries=3
```

Required AI settings remain server-side. Phase 5 additionally supports:

```env
OPENAI_CHAT_MAX_RETRIES=2
OPENAI_QUERY_REWRITE_MODEL=
OPENAI_MODEL_PRICING_JSON={}
RAG_MESSAGE_MAX_LENGTH=4000
RAG_HISTORY_CHARACTER_LIMIT=12000
RAG_EVIDENCE_CHARACTER_LIMIT=16000
RAG_QUERY_REWRITE_MAX_OUTPUT_TOKENS=120
RAG_REQUESTS_PER_MINUTE=12
```

The model pricing JSON shape is a map keyed by approved model name. Values contain `input_per_million_minor`, `output_per_million_minor`, and a three-letter `currency`. It is optional and should be maintained by operators as provider pricing changes.

MySQL remains the only vector backend. Similarity is calculated in PHP after indexed subscriber and bot filtering and lazy database iteration, as documented in `KNOWLEDGE_TRAINING.md`.
