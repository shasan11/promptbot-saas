# Superadmin Functional Release

This release replaces the remaining presentation-only superadmin surfaces with database-backed operational workflows.

## Added

- Payment ledger and refund management.
- Tenant support-ticket lifecycle and internal notes.
- Date-filtered operational and financial reports with CSV exports.
- System health checks and failed-job controls.
- Runtime platform settings, mail testing, and configurable branding.
- Editable tenant primary subdomains.
- Payment and ticket feature tests.

## Improved

- Dashboard now shows live billing and support activity.
- Invoice defaults use configured currency, tax rate, and numbering prefix.
- Invoice settlement is reconciled from net linked payments after refunds.
- Reports keep monetary totals separated by currency.
- Read-only users no longer see unsupported settings or financial actions.

## Removed

- Obsolete generic ConsolePage controller and React placeholder page.
