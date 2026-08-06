# Functional Superadmin Scope

## Tenant management

- Create and provision tenant workspaces.
- Generate or edit the verified primary subdomain.
- Search by company, slug, or domain.
- Change plans while synchronizing the active subscription.
- Suspend, activate, retry provisioning, migrate, seed, or delete a tenant under permission control.

## Plans and subscriptions

- Existing plan CRUD and feature assignment remain active.
- Existing subscription filters, plan changes, status changes, cancellation, and tenant-plan synchronization remain active.

## Payments and invoices

- Create, edit, filter, and inspect payment records.
- Link payments to tenants, invoices, and subscriptions with tenant-ownership validation.
- Record pending, paid, and failed payment states.
- Record partial or full refunds with locking and over-refund prevention.
- Reconcile invoice status from net linked payments after refunds.
- Create, inspect, mark paid, and void invoices.
- Apply configured currency, tax rate, and invoice prefix.

## Tickets

- Create, filter, assign, prioritize, and categorize tenant support tickets.
- Track requester information and SLA deadlines.
- Move tickets through open, pending, resolved, and closed states.
- Add customer-visible replies and private internal notes.

## Reports

- Filter reporting by date range.
- Review tenant, subscription, invoice, payment, plan, and ticket summaries.
- Keep financial breakdowns separated by currency.
- Export tenants, subscriptions, invoices, payments, and tickets as CSV.

## Website customization

- Existing section-based page editor remains available.
- Manage pages, navigation, footer links, media, site settings, and publishing states.

## System health

- Check central database, cache, storage, queue, application environment, and disk space.
- Inspect pending and failed jobs.
- Retry or forget individual failures, retry all failures, flush failure records, and clear application caches under maintenance permission.

## Settings and branding

- General identity, URL, support contact, timezone, locale, and currency.
- Security limits and password-expiry policy.
- Email sender and reply-to identity.
- SMTP transport and test-email delivery.
- Payment defaults and encrypted provider credentials.
- AI provider, model, API endpoint/key, embeddings, vector store, and RAG chunk/retrieval settings.
- Logos, favicon, colors, company name, email logo, and footer copy.
- Runtime application of platform identity, localization, mail, payment, AI/RAG, and branding configuration.
