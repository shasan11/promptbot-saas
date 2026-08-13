# Billing Architecture

Billing is owned by a Customer Account, while a subscription normally belongs to one service. This removes the former one-tenant/one-customer assumption without discarding historical tenant links.

## Data ownership

- `subscriptions.customer_account_id` identifies the payer and `tenant_id` identifies the covered service.
- `billing_profiles` stores legal name, email, tax identifier, address, currency, and invoice-delivery data separately from login profiles.
- `invoices.customer_account_id` is mandatory for new account billing. `tenant_id` is populated for per-service invoices and null for consolidated invoices.
- Invoice items can reference their tenant, subscription, and plan, so a consolidated document remains auditable by service.
- Invoice billing snapshots preserve the issued legal details even after a profile changes.
- Payments and refunds retain account and invoice associations; payment allocation stays historical. Refund and recurring-invoice idempotency keys prevent duplicate financial records.
- `subscription_events` records old/new plan and interval, effective timing, actor, reason, and metadata.
- `payment_attempts` is the provider-neutral, idempotent boundary for invoice payment and failed-payment retry requests.
- Coupons support plan scope, validity/redemption limits, and once/forever/repeating duration; invoice items and redemption records preserve the historical discount.

## Modes and calculations

Per-service billing creates an invoice for one subscription/service. Consolidated billing creates one account invoice with item-level service associations. Both use the same invoice service and calculation path, and the Superadmin invoice/payment forms support either scope.

MRR normalizes annual subscriptions by dividing annual price by 12. ARR is MRR multiplied by 12. Only recurring active/trial-eligible states are included; one-time payments and invoice totals are not treated as recurring revenue.

Invoice totals are calculated from immutable item quantity and unit amount, then discount and tax. The applicable billing profile is copied into `billing_snapshot`. Currency is explicit and values are stored as decimal amounts.

## Subscription lifecycle

Immediate plan changes calculate the unused fraction of the current billing period. The configured proration policy can disable adjustments, issue an immediate upgrade invoice, or carry a signed adjustment to the next renewal. Downgrade credits are carried forward, multiple pending adjustments are accumulated, and proration lines are excluded from coupon discounts.

Plan changes may be immediate or scheduled for period end. Scheduled changes populate pending plan/interval/effective fields and do not rewrite the current plan prematurely. Cancellation supports immediate or period-end behavior; resume clears a pending cancellation. Every mutation creates an event and customer-account activity entry inside a database transaction.

The hourly `subscriptions:process-lifecycle` scheduler applies due changes and cancellations, advances trial/renewal periods, creates idempotent renewal invoices, and moves overdue subscriptions into `past_due` after the configured grace period. Billing-driven suspension is marked in subscription metadata so payment recovery can reactivate only a billing-suspended tenant, never one suspended later by an administrator.

Controllers authorize the subscription and delegate lifecycle rules to `SubscriptionChangeService`. Invoice construction is delegated to `InvoiceService`; account summaries are delegated to `CustomerPortalService`.

## Gateway boundary

Stored portal payment methods contain provider references and display-safe metadata only. Raw card numbers or secrets are never stored. Provider-backed methods can be added from an opaque client-side token, made default, or removed. The manual gateway instead presents remittance/support instructions. Payment and retry clicks create one `payment_attempts` row per account/invoice/idempotency key.

Provider adapters post normalized events to `/billing/webhooks/{provider}` with an `X-PromptBot-Signature` HMAC-SHA256 signature over the raw JSON body. Required normalized fields are `id`, `type`, `invoice_number`, `payment_reference`, `status`, `amount`, and `currency`; failed events may add `failure_reason`. Event IDs and payload hashes are persisted in `payment_webhook_events`, making retries idempotent and rejecting event-ID reuse with a changed payload. Provider checkout adapters remain responsible for translating their native event format into this boundary.

Portal invoice detail includes billing snapshots, item-level workspace attribution, discounts, tax, payments, outstanding state, a dependency-free PDF download, and a provider/manual Pay action. Failed payment rows expose Retry, Update method, and Contact support actions; tax inclusion, grace period, plan timing, and immediate-cancellation behavior are settings-driven.

## Backfill

The additive migration first creates nullable account foreign keys, creates one deterministic legacy Customer Account per existing tenant, then backfills subscriptions, invoices, payments, and support tickets. Existing tenant links remain valid. Operators should compare null-account counts and revenue totals before enabling portal billing.
