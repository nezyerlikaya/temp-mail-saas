# Shared Hosting Deployment Checklist

## Before Upload

- Confirm `.env` has production values.
- Confirm `APP_KEY` is set.
- Confirm `APP_DEBUG=false`.
- Confirm database credentials work.
- Confirm writable paths: `storage`, `storage/app`, `storage/framework`, `storage/logs`, and `bootstrap/cache`.

## After Upload

- Run migrations through the hosting control panel or SSH if available.
- Warm configuration only if the host supports it safely.
- Visit `/health`, `/status`, and `/up`.
- Confirm `/admin` returns a protected response.
- Confirm `/inbox` loads.

## Rollback Notes

- Keep the previous application archive until launch verification completes.
- Keep a database backup before migrations.
- Restore files and database together if rollback is required.
