# Plan limits integration

Subscription plan limits are stored in the `plans.limits` JSON column. The supported keys are defined once in `App\Models\Plan::LIMITS`:

- `ai_answers_per_month`
- `chatbots_limit`
- `knowledge_bases_limit`
- `knowledge_sources_limit`
- `team_members_limit`
- `storage_mb_limit`

A value of `0` means unlimited. Application code must use `App\Services\PlanUsageService`, `PlanLimitService`, or the corresponding plan/subscription methods instead of comparing raw values.

The current tenant root is the subscriber owner `user_id`. Each state-changing service resolves the active immutable subscription snapshot and checks usage while holding the owner or usage-counter row lock. Bot creation/activation enforces chatbot and knowledge-base capacity. Source creation enforces source capacity. File upload reserves exact bytes before private storage. Public/subscriber AI requests reserve an answer before OpenAI is called.

Monthly AI usage uses an idempotent, atomic ledger keyed by tenant, period, and visitor-message event. Monthly subscriptions use their authoritative period. Yearly subscriptions use monthly anniversary windows. Trials use their active trial window. Successful persisted AI messages commit one unit; failed/cancelled work and static failure responses release it. Human replies never create an AI ledger entry.

Knowledge-source usage includes all non-deleted records, including queued and failed sources, because they occupy a plan resource and may retain a private file. Storage counts only private knowledge files through `file_size_bytes`; public theme assets and unrelated attachments are excluded. Bots are the current knowledge-base boundary, so active bot and knowledge-base counts are identical until a separate knowledge-base model is introduced. The workspace owner is excluded from team-member usage; no team membership/invitation write boundary exists in this repository yet.

`Plan::availableForSelection()` is the server-side scope for active pricing selections. `Plan::checkoutEligible()` and `Plan::supportsAutomatedCheckout()` additionally exclude trial and contact-sales plans. Future public pricing and checkout controllers must use these contracts and must recheck eligibility immediately before creating provider checkout state.

Plan deletion checks known subscription and billing tables for `plan_id` references. Future migrations for those tables must add restrictive foreign keys and immutable plan snapshots; they must never cascade billing history when a plan is deleted.
