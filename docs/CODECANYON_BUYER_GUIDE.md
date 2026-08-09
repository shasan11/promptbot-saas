# PromptBot 1.0.0 — Buyer Guide

PromptBot is a self-hosted, multi-tenant omnichannel helpdesk built with Laravel, React, Inertia, and MySQL. It does not generate replies, classify, summarize, route, score sentiment, or execute automation with AI. Routing and automation are deterministic rules configured by administrators.

## Server requirements

- PHP 8.3+ with OpenSSL, PDO MySQL, Mbstring, Tokenizer, XML, Ctype, JSON, Fileinfo, and cURL; BCMath recommended.
- MySQL 8.0+ or MariaDB 10.6+, URL rewriting, HTTPS, cron, and a supervised queue worker.
- Writable `storage`, `storage/app`, `storage/framework`, `storage/logs`, `bootstrap/cache`, and `.env`.
- Wildcard DNS/TLS such as `*.support.example.com` for tenant subdomains.

## Installation

1. Upload outside the public web root when possible and point the domain document root to `public/`.
2. Copy `.env.example` to `.env`; set `APP_URL`, `CENTRAL_DOMAINS`, `TENANT_BASE_DOMAIN`, databases, mail, queue, cache, and HTTPS session values.
3. Visit `/install`. The packaged installer checks PHP/extensions and permissions, accepts the optional purchase-code placeholder, tests the central database, writes application settings, creates the superadministrator, tests mail, selects manual/MySQL/cPanel tenant provisioning, and reports queue/scheduler commands.
4. The installer runs migrations/seeders and writes `storage/installed`; installer endpoints reject later requests.
5. Run `php artisan storage:link` and `php artisan optimize`.
6. Add cron: `* * * * * cd /absolute/path && php artisan schedule:run >> /dev/null 2>&1`.
7. Supervise: `php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600`.
8. Create a plan and tenant, then verify tenant domain, inbound email, web widget, and outbound webhook delivery.

Never expose `.env`, `storage`, `vendor`, source maps, dumps, or logs. Disable directory listing and keep `APP_DEBUG=false`.

## cPanel and VPS

On cPanel, create the central database/user, configure primary and wildcard domains to `public`, add the scheduler, and use Supervisor where available. Use `TENANT_DB_PROVISIONING_MODE=cpanel` only with a restricted database API token. See `CPANEL_SETUP.md`.

On a VPS use PHP-FPM, HTTPS, a wildcard server name/alias, and systemd/Supervisor. Nginx must route missing paths to `public/index.php`; Apache needs `mod_rewrite` and `AllowOverride All`. Restart workers after updates.

## Operations and backups

The scheduler runs snooze release, SLA evaluation/escalations, signed webhook delivery/retry, and knowledge maintenance. `/up` is the lightweight health endpoint; Superadmin → Operations → Health verifies database, cache, storage, queue settings, and production safety.

Back up the central database, every tenant database, `.env`, and uploads. Encrypt off-site copies and test restoration. Diagnostics may include PromptBot/PHP/Laravel versions, sanitized configuration, scheduler/worker state, failed-job count, and redacted logs—never secrets or customer bodies.

## Updates and rollback

1. Read `CHANGELOG.md`, back up, verify restoration, and stage the update.
2. Replace files while preserving `.env`, `storage`, and uploads.
3. Run `composer install --no-dev --optimize-autoloader` and include the shipped production assets (or run `npm ci && npm run build`).
4. Run `php artisan promptbot:update --confirm`. It locks updates, enables maintenance, migrates central/all tenant databases, refreshes permissions, optimizes caches, and exits maintenance mode.
5. Restart workers and smoke-test. Rollback requires restoring matching files and every database; schema downgrade is not automatic.

## Onboarding and demo

Complete branding, invite teammates, create teams, connect a channel, import customers, set business hours/SLA, add saved replies, publish forms/help center, configure deterministic routing/automation, and test with an Agent role.

For demos set `DEMO_MODE=true`, allow-list IDs in `DEMO_TENANT_IDS`, and run `php artisan demo:reset <tenant-id>`. The command refuses non-allow-listed tenants. Never use real credentials or customer data.

## Privacy, security, and troubleshooting

API keys are hash-only and shown once. Channel/webhook secrets are encrypted. Webhooks are HMAC-signed with bounded retry. Public entry points are throttled or signed. Review destructive privacy requests before execution.

- Tenant 404: verify wildcard DNS/TLS, tenant base domain, domain record, and central-domain exclusions.
- No queued work: verify queue connection, worker, and `queue:failed`.
- SLA idle: verify cron, business-hour timezone/intervals, holidays, pause statuses, and policy priority.
- Inbound email rejected: verify raw-body HMAC, timestamp tolerance, rotated secret, and URL.
- Widget rejected: allow the exact origin and verify its public key.
- Webhook failed: use a public HTTPS URL and verify `X-PromptBot-Signature` against the raw body.

## Credits

PromptBot uses Laravel, React, Inertia.js, Tailwind CSS, Lucide, Stancl Tenancy, Spatie Laravel Permission, Laravel Sanctum, Tighten Ziggy, Headless UI, and Symfony components under their respective licenses. Run `composer licenses` and inspect npm licenses for shipped versions. Buyer-provided providers and hosting remain the buyer’s responsibility.
