# Tenancy Operations

PromptBot preserves Stancl Tenancy and separate tenant databases. Tenant provisioning, retries, migrations, and seeders are represented by `platform_operations` and dispatched as queued jobs.

Do not run destructive tenant database work from HTTP controllers. Use the superadmin operation actions or CLI commands, and keep queue workers running in production.
