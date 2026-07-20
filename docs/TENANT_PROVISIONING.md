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

Provisioning is synchronous by default and guarded by cache locks. Failed steps are written to `provisioning_logs` with credentials redacted.

Useful commands:

```bash
php artisan tenant:create --company="Acme" --slug=acme --owner-name="Owner" --owner-email=owner@example.com --owner-password="secure-password" --mode=manual --db-name=acme_db --db-username=acme_user --db-password=secret
php artisan tenant:retry acme
php artisan tenant:health acme
```
