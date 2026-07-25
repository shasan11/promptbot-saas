# Deployment

Production recommendations:

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
```

Required services:

- queue worker: `php artisan queue:work --tries=3 --timeout=120`
- scheduler cron: `* * * * * php artisan schedule:run`
- writable storage/cache directories
- configured central domains and tenant base domain
- configured mail, storage, backup, and provider credentials

Before launch, run:

```bash
php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan promptbot:production-check
npm run build
```
