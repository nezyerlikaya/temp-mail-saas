# VPS Deployment Checklist

## System Requirements

- PHP version compatible with the project.
- Required PHP extensions enabled.
- Web server points to `public`.
- Database server reachable from the app.
- Queue worker supervisor available if async queues are enabled.

## Deployment Review

- Deploy code.
- Install dependencies.
- Configure `.env`.
- Run migrations.
- Configure queue workers.
- Configure scheduler cron.
- Verify health, status, admin protection, inbox, webhooks, and logs.

## Scaling Notes

- Prefer Redis for cache and queues when traffic grows.
- Keep database backups and application archives for rollback.
- Monitor queue lag and provider webhook failures after go-live.
