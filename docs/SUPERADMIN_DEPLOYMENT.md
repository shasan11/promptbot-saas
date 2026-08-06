# Functional Superadmin Deployment

This branch adds central payment/refund, support-ticket, reporting, health, tenant-domain, invoice, runtime-settings, and branding workflows.

## Deployment

Run these commands from the application release directory:

```bash
php artisan migrate --force
php artisan db:seed --class="Database\\Seeders\\PlatformAuthorizationSeeder" --force
php artisan optimize:clear
npm ci
npm run build
```

Restart long-running PHP and queue processes after deployment so they load the latest runtime settings:

```bash
php artisan queue:restart
```

Use the process manager configured for the environment to reload PHP-FPM, Octane, Horizon, or queue workers where applicable.

## Initial configuration

Open **Superadmin → General settings** and review:

1. General identity, URL, timezone, locale, currency, and support email.
2. Security limits and password-expiry policy.
3. Email identity and SMTP delivery, followed by **Send test email**.
4. Payment defaults, invoice prefix, tax rate, and encrypted gateway credentials.
5. AI provider, model, embeddings, vector store, chunking, and encrypted API key.
6. Branding logos, favicon, colors, company name, and footer copy.

## Payment provider note

The superadmin payment ledger, invoice settlement, partial/full refunds, validation, reporting, and encrypted provider configuration are database-backed and operational. Live external gateway capture and webhook processing still require the matching provider adapter to call the provider API and post verified events into these workflows.

## Operational checks

After deployment:

- Open **System health** and verify database, cache, storage, queue, application, and disk checks.
- Confirm queue workers are running when the queue driver is not `sync`.
- Create a test tenant and verify its primary subdomain.
- Create an invoice, record partial payments, complete settlement, and test a refund.
- Create a support ticket, assign it, add a reply and internal note, and resolve it.
- Export at least one CSV from **Reports**.
