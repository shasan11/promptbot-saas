# PromptBot

PromptBot 1.0.0 is a self-hosted, multi-tenant omnichannel helpdesk built with Laravel 13, React, Inertia, and MySQL. It provides customer management, channels, unified inbox, tickets, tasks, deterministic automation, SLA, forms, portal/help center, CSAT, reporting, quality, workforce basics, and developer/security controls.

PromptBot includes an optional tenant-scoped AI platform powered by Neuron AI. Workspace administrators can configure encrypted providers, version and deploy grounded agents, review inbox copilot suggestions, gate connection tools behind approvals, run evaluations, and monitor usage. AI remains disabled by plan or workspace policy when not configured, and the helpdesk continues to operate without it.

## Documentation

- [CodeCanyon buyer guide](docs/CODECANYON_BUYER_GUIDE.md)
- [Installation](docs/INSTALLATION.md)
- [cPanel setup](docs/CPANEL_SETUP.md)
- [Multitenancy](docs/MULTITENANCY.md)
- [Tenant provisioning](docs/TENANT_PROVISIONING.md)
- [Upgrade guide](docs/UPGRADING_TENANTS.md)
- [Release checklist](docs/RELEASE_CHECKLIST.md)
- [Changelog](CHANGELOG.md)
- [Tenant AI platform](docs/AI_PLATFORM.md)
- [AI implementation report](docs/AI_IMPLEMENTATION_REPORT.md)

## Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan test
```

For production, follow the buyer guide, run a persistent queue worker, invoke `schedule:run` every minute, keep `APP_DEBUG=false`, and back up the central database, every tenant database, `.env`, and uploads.
