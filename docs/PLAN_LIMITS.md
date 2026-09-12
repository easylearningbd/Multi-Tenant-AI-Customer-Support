# Plan limits integration

Subscription plan limits are stored in the `plans.limits` JSON column. The supported keys are defined once in `App\Models\Plan::LIMITS`:

- `ai_answers_per_month`
- `chatbots_limit`
- `knowledge_bases_limit`
- `knowledge_sources_limit`
- `storage_mb_limit`

A value of `0` means unlimited. Application code must use `App\Services\PlanLimitService` or the corresponding `Plan` methods instead of comparing raw values.

The workspace, subscription, chatbot, knowledge-base, source-ingestion, storage-metering, and AI-usage domains do not exist in the current repository. When they are added, each state-changing service must resolve the workspace's active subscription and its immutable plan snapshot, calculate tenant-scoped current usage, and call `PlanLimitService::ensureAllows()` inside the same transaction or lock used to create/reserve usage.

Monthly AI usage requires an idempotent, atomic usage ledger keyed by workspace, billing period, and source event. Do not implement it as an in-memory count or a client-supplied total.

`Plan::availableForSelection()` is the server-side scope for active pricing selections. `Plan::checkoutEligible()` and `Plan::supportsAutomatedCheckout()` additionally exclude trial and contact-sales plans. Future public pricing and checkout controllers must use these contracts and must recheck eligibility immediately before creating provider checkout state.

Plan deletion checks known subscription and billing tables for `plan_id` references. Future migrations for those tables must add restrictive foreign keys and immutable plan snapshots; they must never cascade billing history when a plan is deleted.
