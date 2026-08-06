# Superadmin Troubleshooting

## New pages return database-table errors

Run central migrations:

```bash
php artisan migrate --force
```

## Menu items return 403 for the platform owner

Refresh seeded permissions and clear permission/cache state:

```bash
php artisan db:seed --class="Database\\Seeders\\PlatformAuthorizationSeeder" --force
php artisan optimize:clear
```

## Frontend page cannot be resolved

Install/build the frontend assets:

```bash
npm ci
npm run build
```

## Settings save but workers use old values

Clear caches and restart long-running processes:

```bash
php artisan optimize:clear
php artisan queue:restart
```

Reload PHP-FPM, Octane, or Horizon through the environment process manager when used.

## Test email fails

- Confirm mailer, host, port, username, password, and encryption.
- Confirm sender identity is accepted by the SMTP provider.
- Review application logs for the transport exception.

## Tenant hostname does not resolve

- Confirm the primary domain in Tenant Management.
- Confirm wildcard DNS points to the application.
- Confirm the web server accepts the wildcard hostname.
- Clear route/config caches after domain configuration changes.

## Invoice does not become paid

- Confirm linked payment currency matches the invoice currency.
- Confirm linked payment status is paid, partially refunded, or refunded.
- Confirm the net linked payment total after refunds reaches the invoice total.
