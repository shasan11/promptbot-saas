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

## Subdomain routing

Tenants are identified purely by hostname via Stancl's `InitializeTenancyByDomain` middleware (see `routes/tenant.php`) — there is no slug-in-path or query-string identification anywhere in the app. Each tenant has one or more rows in the `domains` table; the primary one is what a tenant actually visits.

When a tenant is created (or edited) without an explicit subdomain, the app builds one automatically as `{slug}.{tenant_base_domain}`. The base domain is controlled from **Superadmin → General settings → Tenant base domain** (`platform_settings` group `general`, key `tenant_base_domain`), not a hardcoded value — changing it only affects newly generated subdomains, not existing tenants' domain records.

### Local development

Set the base domain to `localhost`. Modern Chromium and Firefox resolve any `*.localhost` hostname to `127.0.0.1` automatically — no hosts-file edit or local DNS server needed. A tenant with slug `acme` is reachable at `http://acme.localhost:8000` (adjust the port to match `php artisan serve` or your dev server). If you're on an older browser or a corporate-locked machine where this doesn't resolve, add an explicit entry per tenant to your hosts file instead (`C:\Windows\System32\drivers\etc\hosts` on Windows):

```
127.0.0.1 acme.localhost
```

### Production

Set the base domain to your real domain (e.g. `tenants.yourapp.com` or just `yourapp.com`) and:

1. Add a wildcard DNS record: `*.yourapp.com` → your server's IP (an `A`/`AAAA` record, or a `CNAME` if fronted by a load balancer/CDN).
2. Provision a wildcard TLS certificate covering `*.yourapp.com` (e.g. via Let's Encrypt DNS-01 challenge — HTTP-01 can't validate wildcards) and configure your web server/proxy to serve it for all tenant hosts.
3. Make sure `CENTRAL_DOMAINS` (`.env`) lists only your actual central/admin hostnames — never the wildcard — so `EnsureCentralDomain` and `PreventAccessFromCentralDomains` keep central and tenant traffic correctly separated.

### Known gotcha: `asset_helper_tenancy`

`config/tenancy.php`'s `filesystem.asset_helper_tenancy` is set to `false` in this app. Stancl's default (`true`) rewrites the global `asset()` helper to route through `/tenancy/assets/...` whenever tenancy is initialized, which also hijacks Vite's compiled JS/CSS bundle URLs and breaks every tenant-subdomain page (404s on all assets). If you need genuinely tenant-scoped stored files (avatars, uploads), use the `tenant_asset()` helper explicitly for those instead of re-enabling this flag.
