# cPanel Setup

Set:

```env
TENANT_DB_PROVISIONING_MODE=cpanel
CPANEL_HOST=https://server.example.com:2083
CPANEL_USERNAME=account
CPANEL_API_TOKEN=...
CPANEL_DATABASE_PREFIX=account
CPANEL_DATABASE_USER=account_appuser
CPANEL_VERIFY_SSL=true
```

Create the API token in cPanel, not a password. The token must allow MySQL UAPI calls. The database user must already exist and be assignable to new tenant databases.

The provisioner calls:

- `/execute/Mysql/create_database`
- `/execute/Mysql/set_privileges_on_database`

If UAPI is unavailable, use manual database mode.
