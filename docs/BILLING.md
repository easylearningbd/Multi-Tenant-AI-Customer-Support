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

`PlanUsageService` is the authoritative usage and entitlement boundary. Billing renders one summary
from this service rather than calculating values in Blade. AI answers come from committed,
idempotent usage-ledger entries in the active monthly window. Active bots are both the chatbot and
knowledge-base count because this version has no separate knowledge-base root model. Every
non-deleted knowledge source consumes source capacity, and knowledge storage is the sum of the
original private file byte sizes. The owner is not counted as a team member; team usage remains zero
until the membership domain is introduced.

Limit value `0` is the established unlimited convention. Storage is enforced in bytes even though
plans configure megabytes. AI and storage work reserve quota atomically before the expensive action,
then commit or release the reservation. `usage_counters` contains current aggregate/reservation state,
while `usage_ledgers` remains the append-oriented audit record. Resource counts are always read from
their authoritative tenant-owned tables.

Run `php artisan usage:reconcile` to rebuild current counters for all subscribers, or pass
`--tenant=<subscriber-id>` for one owner. The command is idempotent, never reads document content,
and is scheduled daily at 02:15. A queue worker remains required for `ai-responses`,
`knowledge-ingestion`, and the other configured application queues.

## Manual Bank Transfer

The subscriber Billing page allows an eligible active monthly or yearly plan to enter the manual
Bank Transfer workflow. The server reloads and locks the plan, derives the expected minor-unit
amount and currency, and stores an immutable plan snapshot. Browser values cannot change those
terms. Trial, custom-priced, zero-priced, inactive, current, and currency-mismatched plans are
rejected server-side.

Configure Bank Transfer before enabling it:

```env
BANK_TRANSFER_ENABLED=true
BANK_TRANSFER_BANK_NAME="Example Bank"
BANK_TRANSFER_ACCOUNT_NAME="NeuralDesk"
BANK_TRANSFER_ACCOUNT_NUMBER=
BANK_TRANSFER_BRANCH=
BANK_TRANSFER_ROUTING_NUMBER=
BANK_TRANSFER_SWIFT_CODE=
BANK_TRANSFER_IBAN=
BANK_TRANSFER_CURRENCY=USD
BANK_TRANSFER_INSTRUCTIONS=
BANK_TRANSFER_PROOF_DISK=local
BANK_TRANSFER_PROOF_MAX_KB=10240
BANK_TRANSFER_SUBMISSION_RATE_PER_MINUTE=3
```

Bank name, account name, account number, and a three-letter currency are required. Missing or
incomplete configuration disables plan selection. Optional fields are omitted from the page rather
than rendered blank. Proofs use the private `local` disk by default under generated
`payment-proofs/{billing-owner}` paths and are available only through an authenticated,
owner-authorized download controller.

Submission creates one pending `payments` record, one pending `invoices` record, and one
`payment_attachments` proof record in a transaction. An owner-row lock prevents equivalent pending
submissions from being duplicated. Filesystem cleanup runs when database persistence fails. A
pending payment never changes the current subscription.

The domain actions `ApproveBankTransferPayment` and `RejectBankTransferPayment` are intentionally
not exposed by routes yet. Both require an Admin reviewer, lock the payment, and are idempotent.
Approval pays the invoice and activates one manual-period subscription. A different plan starts
immediately and closes the previous entitlement without deleting history. Renewal of the same
manual plan extends from the later of the current period end or approval time. Monthly and yearly
period calculations avoid calendar overflow. Upgrades retain the current monthly AI-usage anchor,
so approval does not reset consumed quota. Downgrades never delete resources; over-limit totals are
shown and new protected writes are blocked. Rejection preserves the current subscription and
stores the subscriber-visible reason.

Payment submission and review notifications are dispatched after their database transaction has
committed. The queue worker must process the `notifications` queue outside local synchronous queue
environments.

## Future gateway boundary

Stripe is shown as disabled and has no route, SDK, key, webhook, or payment mutation. A future
gateway implementation must accept only a plan identifier, reload an eligible plan, use its stored
minor-unit price and currency through a gateway contract, and activate a subscription only after an
idempotently verified webhook. Provider checkout must retain the purchase snapshot and must not
reuse the subscriber-entered Bank Transfer amount as an authoritative price.
