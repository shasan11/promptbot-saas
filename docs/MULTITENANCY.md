# Multitenancy

PromptBot uses `stancl/tenancy` database-per-tenant mode. Central platform tables live in `database/migrations`; tenant tables live in `database/migrations/tenant`.

Central tables include tenants, domains, central users, plans, features, subscriptions, feature overrides, installation settings, queues, cache, sessions, and provisioning logs.

Tenant databases include users, password resets, sessions, roles, permissions, settings, notifications, activity logs, and application business tables.

## Commands

```bash
php artisan migrate --force
php artisan tenants:migrate --force
php artisan tenants:seed --class=Database\\Seeders\\TenantDatabaseSeeder --force
php artisan tenant:migrate acme
php artisan tenant:seed acme
```

Do not add `tenant_id` to shared business tables as the isolation boundary.
