# Superadmin Control Plane

The `/superadmin` application is the operator control plane. It uses the `central` guard and is isolated from customer portal and tenant identities.

## Modules

- Dashboard: real customer, service, MRR, ARR, revenue, outstanding balance, failed-payment, and trial metrics.
- Customers: account search/filter, portal-user directory, and Customer 360 with owners, members, services, subscriptions, invoices, payments, tickets, notes, and activity.
- Services: tenant lifecycle, account ownership, provisioning status/logs, suspension, activation, retry, and service details. The legacy `/tenants` routes remain for compatibility.
- Revenue and growth: normalized recurring metrics, collections, refund/failure signals, plan mix, account signups, trials, conversion, and churn trends.
- Refunds: a dedicated searchable register with original, refunded, and remaining amounts; refunds require an amount and reason, retain provider/audit references, and reject duplicate idempotency keys.
- Support and operations: existing queues, filterable real usage, a dedicated provisioning monitor with retry/log drill-through, jobs, backups, maintenance, and audit surfaces.
- Website & CMS: pages/blocks, revisions, preview, media, navigation, redirects, theme, and SEO.
- Security: platform administrator invites, activation/suspension safeguards, role assignment, password access reset, required-2FA flags, session revocation, role/permission editor, audit log, and login activity.
- Configuration: plans, coupons, features, billing, gateway, registration, portal, mail, branding, security settings, and editable lifecycle email templates with sandboxed preview and test delivery.
- Search and quick create: permission-aware global lookup across accounts, users, services, subscriptions, invoices, and tickets, plus account/service quick actions.

## Roles and permissions

The authorization seeder creates Platform Owner, Platform Administrator, Billing Manager, Support Manager, Content Manager, Operations Manager, Auditor, and the backward-compatible Read-Only Auditor. Permissions are granular (`customers.*`, `revenue.view`, `website.*`, billing, operations, security, and administration).

The final Platform Owner cannot be suspended. An administrator cannot suspend their own active session. Sensitive account and subscription changes are recorded in account activity or platform audit history. Custom HTML is separately permission-gated.

The email-template editor seeds invitation, verification, password-reset, provisioning, invoice, payment, and support templates. Invitation, verification, and password reset notifications use active templates immediately and fall back to code defaults during installation or when a template is inactive.

### Email template variables

- Common identity/action variables: `platform_name`, `customer_name`, `account_name`, `action_url`.
- Workspace lifecycle: `workspace_name`.
- Invoices: `invoice_number`, `invoice_total`.
- Payments: `payment_amount`.
- Support: `ticket_number`.

Each editor displays only the variables allowed for that template. Unknown variables render as blank, previews run in a sandboxed iframe, and executable template code is not evaluated.

## Customer and service operations

New services must select a Customer Account. Customer 360 is the canonical cross-service view and displays only persisted values—no fabricated dashboard totals. Operators can issue service-specific or consolidated account invoices and record matching account-level payments. Internal customer notes are central-only. Tenant provisioning keeps stage logs and sanitized errors; retry uses the existing tenant and reconciles its purchase request instead of creating a duplicate.

Customer impersonation is intentionally not exposed. It remains an optional future capability and must not be added without password reconfirmation, a reason, a short-lived signed session, an obvious banner, one-click exit, and complete auditing.

Customer 360 owns explicit account-wide limit records. These remain separate from per-service plan limits. Workspace-count limits are enforced during workspace purchase; other account metrics can use stable feature keys without changing service-plan semantics.

## Deployment

Before rollout, back up the central and all tenant databases. Deploy code, run `php artisan migrate --force`, `php artisan db:seed --class=PlatformAuthorizationSeeder --force`, clear caches, cache routes/config, build frontend assets, and restart queue workers. Validate legacy tenant account links and inspect provisioning failures before opening customer registration.

## Stakeholder demo

1. Sign in at `/superadmin/login` and review real dashboard KPIs.
2. Open Customers, select an account, and walk through its services, billing, tickets, notes, and activity.
3. Create a service for that account and show provisioning stages.
4. Open Revenue and explain annual-to-monthly normalization and outstanding balances.
5. Open Security, demonstrate the seeded roles, and show audit/login activity.
6. Edit the home page, use signed preview, publish, and verify the public page and sitemap.
