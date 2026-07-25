# Superadmin

The superadmin console is served under `/superadmin` and uses central authentication only. Routes are split by domain in `routes/superadmin/` and are protected by `central.domain`, `auth:central`, `central.active`, `central.2fa`, and granular permission middleware.

Major modules include dashboard, tenants, billing resources, platform resources, website resources, communications, support, operations, administration, security, and settings. Large lists use server-side pagination/search/sorting from central tables.

The generic `ConsolePageController` placeholder module has been removed.
