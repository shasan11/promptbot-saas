# Superadmin Security Notes

- Every visible route uses explicit central-guard permissions.
- Sensitive SMTP, payment-gateway, webhook, and AI credentials are encrypted through the existing PlatformSetting cast and are never returned to the browser after saving.
- Payment refunds use database transactions and row locking to prevent concurrent over-refunds.
- Invoice and subscription links are validated against the selected tenant.
- Financial records are adjusted through payment states, refunds, invoice settlement, and voiding rather than destructive deletion.
- Ticket internal notes are stored separately from customer-visible replies.
- Cache and failed-job destructive actions require maintenance permission and create audit events.
- Tenant subdomains use uniqueness validation and the normalized Domain model.
- CSV exports are protected by authenticated central routes and view permissions.
