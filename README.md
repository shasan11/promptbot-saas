# PromptBot SaaS

PromptBot is a Laravel, Inertia, React, Tailwind CSS, and Stancl Tenancy SaaS application with a central superadmin console and isolated tenant databases.

## Superadmin Access

Public central administrator registration is disabled. Platform administrators are created only by:

- installer or seeder-provided initial owner credentials;
- a trusted CLI/seed workflow;
- invitation from an administrator with `administrators.invite`.

Set initial owner values explicitly when seeding a new environment:

```bash
CENTRAL_ADMIN_NAME="Platform Owner"
CENTRAL_ADMIN_EMAIL="owner@example.com"
CENTRAL_ADMIN_PASSWORD="use-a-long-random-password"
php artisan db:seed --class=CentralUserSeeder
php artisan db:seed --class=PlatformAuthorizationSeeder
```

Platform Owner accounts require two-factor authentication.

## Operations

High-impact tenant actions create `platform_operations` rows and dispatch queued jobs. Production should run a real queue worker:

```bash
php artisan queue:work --tries=3 --timeout=120
```

The scheduler must run every minute:

```bash
* * * * * php artisan schedule:run
```

## Production Check

Run:

```bash
php artisan promptbot:production-check
```

Recommended production values:

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
```

Provider credentials for mail, payment gateways, AI, voice, ecommerce, storage, backups, and cPanel-style provisioning must be configured in the relevant settings/provider screens before those integrations can process real traffic.

More details live in `docs/`.
