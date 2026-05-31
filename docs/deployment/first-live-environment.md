# First Live Environment Checklist

## Shared Hosting Steps

- Upload the application files.
- Point the web root to `public`.
- Configure `.env` with production values.
- Confirm `APP_ENV=production`, `APP_DEBUG=false`, and `APP_KEY` is set.
- Confirm database credentials.
- Confirm `storage` and `bootstrap/cache` are writable.
- Run migrations through SSH or the host control panel if supported.
- Confirm `storage/app/install.lock` exists after installation.

## VPS Steps

- Configure PHP, web server, database, and process supervisor.
- Install dependencies.
- Configure `.env`.
- Run migrations.
- Configure queue workers when queue driver is not `sync`.
- Configure scheduler cron.
- Run `system:first-live-check`.

## Queue Worker Notes

- Shared hosting can start with conservative scheduled processing.
- VPS deployments should run supervised workers.
- Review failed jobs before increasing provider traffic.

## Scheduler Notes

- Configure Laravel scheduler once per minute if the host supports cron.
- Monitor cleanup, operations metrics, and health schedules after launch.

## Storage Permissions Checklist

- `storage`
- `storage/app`
- `storage/framework`
- `storage/logs`
- `bootstrap/cache`

## Environment Variable Checklist

- `APP_ENV`
- `APP_DEBUG`
- `APP_KEY`
- `APP_URL`
- Database connection values.
- Cache/session/queue/mail drivers.
- Provider webhook secrets only when providers are enabled.

## Provider And Domain Onboarding

- Keep providers disabled until webhook signing and intake flow are tested.
- Keep fallback domains configured until new domains pass inbound tests.
- Validate domain-to-provider mapping before user exposure.

## Rollback Reminder

Keep a pre-launch file archive and database backup. Roll back files and database together if migrations or launch validation fail.
