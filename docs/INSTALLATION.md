# Installation

PromptBot remains installable through `/install` using `froiden/laravel-installer`. The app adds `/install/tenancy/status` checks for tenancy-specific requirements, permissions, license adapter status, and tenant provisioning mode.

## Fresh install

1. Upload the app and create the central MySQL database.
2. Copy `.env.example` to `.env`.
3. Set `APP_URL`, `CENTRAL_DOMAINS`, `TENANT_BASE_DOMAIN`, and central `DB_*`.
4. Visit `/install`.
5. Complete Froiden requirements, permissions, environment, database, and final steps.
6. Run central migrations only:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Tenant migrations are never run by the installer against the central database.

## Required env

```env
CENTRAL_DOMAINS=example.com,www.example.com,admin.example.com
TENANT_BASE_DOMAIN=example.com
TENANT_DB_PROVISIONING_MODE=manual
QUEUE_CONNECTION=sync
```

Use `APP_DEBUG=false` in production.
