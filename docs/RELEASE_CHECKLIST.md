# Commercial Release Checklist

- [ ] `VERSION` and `CHANGELOG.md` match the package.
- [ ] Production Composer install and frontend build succeed from lock files.
- [ ] Central and fresh tenant migrations pass on supported MySQL/MariaDB.
- [ ] Tenant isolation, authorization, inbox, ticket, automation, SLA, API scope, signing, and import rollback tests pass.
- [ ] Production env, HTTPS, secure cookies, non-sync queue, cron, worker, wildcard DNS/TLS are documented.
- [ ] Installer locks after completion and requires no source editing.
- [ ] Backup/restore, update/rollback, demo reset, diagnostics, and troubleshooting are documented.
- [ ] Demo is allow-listed; real secrets and destructive demo actions are disabled.
- [ ] `.env`, logs, caches, test artifacts, node modules, IDE files, maps, and dumps are excluded.
- [ ] Empty/error/loading states and mobile layouts are smoke-tested.
- [ ] No AI support feature, call, prompt, generated reply, classification, summary, routing, or sentiment behavior is enabled.
