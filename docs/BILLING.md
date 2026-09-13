# Subscriber billing foundation

Subscriber billing is owned directly by the authenticated `user` record because the application
does not yet have workspace or membership tables. The ownership boundary is centralized through
`User::isBillingOwner()` and `CurrentSubscriptionResolver`. When workspaces are introduced,
subscriptions and all billing queries must move to the verified workspace owner rather than adding
a second parallel billing system.

## Default trial

Public registration dispatches Laravel's `Registered` event inside the user-creation transaction.
`AssignDefaultTrialToRegisteredSubscriber` then calls `AssignDefaultTrial`. The action locks the
billing owner, selects the configured plan by stable slug, verifies that it is active and has the
Trial interval, and creates one subscription snapshot.

Configure the slug with:

```env
DEFAULT_TRIAL_PLAN_SLUG=free-trial
```

The plan must already exist in the Admin plan system with a positive `trial_days` value. Missing,
inactive, or non-Trial configuration rolls registration back and returns a generic temporary error.
It never falls back to another or paid plan.

`users.trial_claimed_at`, a locked owner row, and the unique subscription `trial_claim_key` prevent
duplicate or repeated trials. Existing subscribers are intentionally not changed by the migration;
there is no automatic backfill.

## Subscription history

The `subscriptions` table keeps status, trial and billing-period timestamps, nullable future
provider identifiers, metadata, and an immutable `plan_snapshot`. Current entitlements use the
snapshot so later plan edits cannot rewrite historical commercial terms. Effective trial and active
status is calculated from timestamps at request time, so an expired period cannot remain entitled
because a scheduled synchronization did not run.

The page currently reports zero usage for AI answers, chatbots, knowledge bases, knowledge sources,
team members, and storage because none of those tenant-owned tables or ledgers exists yet. Each
future aggregate belongs in `SubscriberBillingData::usageFor()` and must query through the verified
billing owner/workspace. The centralized `PlanLimitService` accepts subscriptions and rejects
expired entitlement records.

Invoices remain an empty state because the application has no invoice or payment model. The Free
Trial does not create a fake invoice.

## Future checkout boundary

No checkout or paid plan mutation route exists. A future implementation should add an authorized
`POST /billing/checkout` action that accepts only a plan identifier, reloads an active recurring
plan from the database, uses its stored minor-unit price and currency, creates a provider session
through a gateway contract, and activates a subscription only after an idempotently verified
webhook. It must persist an invoice/payment record scoped to the billing owner and retain the plan
snapshot used for that purchase.
