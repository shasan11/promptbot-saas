# Superadmin Operations

## Daily operations

- Review dashboard payment, invoice, subscription, tenant, and ticket activity.
- Reconcile pending or failed payments and record refunds where required.
- Monitor open and overdue support tickets.
- Review system health and failed queue jobs.

## Financial integrity

- Use payment records for collected funds.
- Use refund records for returned funds.
- Use invoice voiding for invalid invoices.
- Avoid changing payments after a refund; create an adjusting payment instead.
- Review reports by currency rather than combining monetary totals from different currencies.

## Incident response

- Inspect the failed-job exception before retrying.
- Retry a single job first when the failure scope is unclear.
- Flush failed-job records only after confirming they are no longer needed for diagnosis.
- Clear application caches after configuration or deployment changes.
