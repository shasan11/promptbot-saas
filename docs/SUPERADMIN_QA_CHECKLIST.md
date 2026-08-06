# Superadmin QA Checklist

## Access control

- Confirm Platform Owner can access every visible module and action.
- Confirm Read-Only Auditor can view permitted modules without seeing update controls.
- Confirm direct write-route requests return 403 without the required permission.

## Tenant and domain

- Create a tenant using each configured provisioning mode.
- Verify the generated primary hostname is unique and routes to the tenant.
- Edit the primary hostname and confirm the old host no longer resolves through tenancy.
- Change the plan and confirm both tenant and latest subscription reflect the new plan.

## Billing

- Create draft and open invoices.
- Confirm configured invoice prefix, currency, and tax defaults are applied.
- Record partial payments and verify the invoice stays open.
- Complete settlement and verify the invoice becomes paid.
- Record a partial refund and verify the invoice reopens when net settlement is below its total.
- Confirm over-refunds and cross-tenant invoice/subscription links are rejected.

## Tickets

- Create and assign a ticket.
- Verify priority, SLA deadline, requester data, and filters.
- Add a public reply and an internal note.
- Resolve, reopen, and close the ticket while checking timestamps.

## Reports

- Apply a custom date range.
- Check headline values against source records in the configured default currency.
- Verify invoice and payment breakdowns remain separated by currency.
- Download each CSV export and inspect its columns and row counts.

## Website

- Create and publish a page.
- Add, reorder, edit, and remove sections.
- Update navigation, footer, media, public-site settings, and branding.
- Confirm the published central website renders the changes.

## System and settings

- Verify every health check.
- Test queue retry and failure-removal actions with a controlled failed job.
- Save each settings group and confirm secrets remain masked.
- Send a test email.
- Confirm title, favicon, logo, and colors update after cache/process reload.
