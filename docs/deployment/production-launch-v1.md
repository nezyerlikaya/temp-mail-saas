# Production Launch v1.0.0

## Final Pre-Launch Checklist

- Run `system:v1-launch-status`.
- Confirm certification is `CERTIFIED`.
- Confirm rollback owners are available.
- Confirm no secrets, credentials, or raw payloads are copied into launch notes.

## Production Environment Checklist

- Installer is locked.
- APP_KEY is configured.
- Debug mode is disabled.
- Storage and cache paths are writable.

## Provider Checklist

- Provider readiness has no blockers.
- Webhook signing remains configured through environment variables.
- Duplicate and replay protection remain active.

## Domain Checklist

- Active onboarded domains exist.
- Suspended domains are excluded.
- Domain rollback notes are available.

## Queue Worker Checklist

- Inbound queue name is configured.
- Queue backlog thresholds are known.
- Failed job review is clear.

## Scheduler Checklist

- Scheduler expectations are documented.
- Cleanup and health schedules are reviewed for the hosting environment.

## Monitoring Checklist

- Watch health status, queue backlog, provider failures, webhook failures, inbox polling errors, abuse spikes, billing webhook failures, API errors, and incident count.

## Support Checklist

- Support readiness and triage ownership are reviewed.
- Escalation paths are documented.
- First 24-hour watch owners are assigned.

## Rollback Checklist

- Backup readiness is green.
- Rollback documentation is available.
- Rollback triggers are agreed before launch.

## First 24-Hour Watch Plan

- Watch critical signals continuously.
- Pause expansion on rollback triggers.
- Record decisions without exposing sensitive data.
