# Scheduler Checklist

## Cron

Configure one cron entry to run Laravel scheduler once per minute when supported by the host.

## Review

- Health checks schedule.
- Operations metrics schedule.
- Cleanup schedule.
- Backup schedule if added later.

## Monitoring

Confirm scheduled jobs do not overlap and review operations events after the first production run.
