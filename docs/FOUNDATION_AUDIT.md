# PromptBot Non-AI Commercial Release — Foundation Audit

Audit date: 2026-08-08

## Existing architecture

- Laravel 13, Inertia 2, React 18, MySQL, Sanctum, Spatie Permission, and stancl/tenancy.
- Database-per-tenant isolation initialized by domain middleware.
- Separate central and tenant guards, sessions, permission tables, migrations, and seeders.
- Tenant-aware queue middleware and tenant storage helpers already exist.
- Central SaaS modules cover tenants, plans, subscriptions, invoices, payments, settings, website CMS, platform support, health, and provisioning.
- Tenant modules cover dashboard, users, invitations, teams, departments, roles, workspace settings, business hours, holidays, connections, and knowledge management.
- Installer endpoints and tenant provisioning services already exist.

## Reuse decisions

- Preserve database-per-tenant architecture and existing authentication guards.
- Extend the tenant permission catalog and existing default roles.
- Reuse `TenantAuditLogService`, shared UI primitives, Inertia pagination patterns, business-hour models, teams, users, notifications, tenant-aware jobs, and private storage conventions.
- Add helpdesk tables only to tenant migrations; no tenant-owned operational data belongs in the central database.
- Use public UUIDs in URLs while retaining internal integer foreign keys for efficient joins.

## Material gaps against the release brief

- No customer/contact/company domain, reusable tags, custom fields, customer timeline, or customer import/export.
- No channels, email ingestion/delivery, web-chat widget, conversations, messages, inbox, attachments, assignment, mentions, or snooze.
- No tenant ticketing, task management, routing queues, presence, SLA, escalation, templates, macros, or deterministic automation.
- No customer portal/help center publishing, CSAT, operational reporting, developer API keys/outbound webhooks, quality, or workforce modules.
- Commercial updater, demo reset, complete documentation, credits, packaging, and release validation remain incomplete.
- Tenant navigation currently exposes only Dashboard, Knowledge Base, Administration, and Connections.

## Security findings

- Strong existing controls: tenant domain boundary, separate tenant databases, guarded central administration, signed knowledge downloads, encrypted credential vault, webhook verification, secret redaction, and permission-backed policies.
- New resources must retain tenant database isolation, UUID route keys, private file delivery, server-side validation, per-action permissions, audit records, pagination, and explicit destructive confirmations.
- Public widget/API endpoints will require scoped hashed tokens, rate limits, idempotency, origin controls where appropriate, and no private credential exposure.

## Non-AI scope conflict

The existing Knowledge Base includes embedding, vector retrieval, an OpenAI embedding provider, retrieval playground terminology, and AI-oriented connection grants. These predate this phase but conflict with the stated non-AI product boundary. No new AI functionality will be built. Before commercial release, AI provider wiring and AI-specific marketing/settings must be removed from the non-AI build or isolated behind an unavailable optional extension. Deterministic keyword search may remain.

## Baseline verification

- Frontend dependencies are installed.
- The full PHPUnit suite exceeds a two-minute run because existing tenant integration tests provision databases and several individual cases take 30–120 seconds.
- Verification strategy: targeted tests per module, frontend production build after UI changes, then an extended full-suite release run.
- Pre-existing working-tree changes in `resources/js/Components/UI/Modal.jsx` and temporary `storage/tenanttest-*` directories are user-owned and must not be overwritten or removed.

## Implementation order

Follow the 13 phases in the master brief. Each domain is complete only after schema, models, services, authorization, routes, validation, UI, audit behavior, tenant isolation tests, and relevant background processing are functional.
