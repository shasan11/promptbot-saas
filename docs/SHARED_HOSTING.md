# Shared Hosting

PromptBot does not require root access, Supervisor, Docker, Redis, SSH after upload, or permission to run `CREATE DATABASE`.

## Domains

Configure wildcard subdomains when available:

```text
*.example.com -> public/
```

If wildcard subdomains are unavailable, create each tenant subdomain manually in cPanel and point it to the same public directory.

## Manual database mode

Use this default on shared hosting:

```env
TENANT_DB_PROVISIONING_MODE=manual
QUEUE_CONNECTION=sync
```

Create the database and user in cPanel, assign privileges, then provision the tenant with those credentials.

## Cron

Use the host cron panel:

```bash
* * * * * php /home/account/app/artisan schedule:run >> /dev/null 2>&1
```

Database queues are optional:

```env
QUEUE_CONNECTION=database
```

Run `php artisan queue:work --stop-when-empty` from cron if the host allows it.

## Backups

Use cPanel Backup, phpMyAdmin export, or a host-approved scheduled `mysqldump` per tenant database. Do not enable automatic database deletion unless backups and ownership are verified.
