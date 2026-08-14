# Tenant Provisioning

Provisioning is owned by `App\Services\Tenancy\TenantProvisioningService`.

Steps:

1. Create or reuse the central tenant record.
2. Create or verify the tenant database.
3. Store encrypted tenant database credentials.
4. Run tenant migrations.
5. Run tenant seeders.
6. Create the tenant owner inside the tenant database.
7. Attach the tenant subdomain.
8. Activate the tenant.

Superadmin tenant creation offers two execution modes:

- **Laravel Queue (recommended):** creates the pending central tenant record, encrypts the provisioning payload, and dispatches `ProvisionTenantJob` to the `provisioning` queue.
- **Immediate:** runs the complete workflow in the web request. This is intended for installations that cannot run a queue worker.

The tenant creation page reports whether the configured Laravel queue driver can accept background work. When the queue is unavailable, it displays the environment setting, migration command, and worker command needed to enable it. Failed steps are written to `provisioning_logs` with credentials redacted.

For database queues, configure and supervise a worker with a timeout long enough for first-time tenant migrations:

```bash
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1900
php artisan migrate --force
php artisan optimize:clear
php artisan queue:work database --queue=provisioning,default --tries=1 --timeout=1800
```

Provisioning owner passwords and database credentials are encrypted before being placed in a queue payload.

Useful commands:

```bash
php artisan tenant:create --company="Acme" --slug=acme --owner-name="Owner" --owner-email=owner@example.com --owner-password="secure-password" --mode=manual --db-name=acme_db --db-username=acme_user --db-password=secret
php artisan tenant:retry acme
php artisan tenant:health acme
```
