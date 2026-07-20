# Upgrading Tenants

Apply central migrations first:

```bash
php artisan migrate --force
```

Then apply tenant migrations:

```bash
php artisan tenants:migrate --force
```

For one tenant:

```bash
php artisan tenant:migrate acme
php artisan tenant:seed acme
```

Check health after upgrades:

```bash
php artisan tenants:health
```
