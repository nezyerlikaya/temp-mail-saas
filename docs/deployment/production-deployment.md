# Production Deployment Preparation

## Deployment Checklist

- Run `system:v1-launch-status`.
- Run `system:deployment-readiness`.
- Confirm the installer is locked, `APP_DEBUG` is disabled, and `APP_URL` uses HTTPS.
- Confirm database, cache, session, queue, and filesystem settings for the target environment.
- Keep credentials in environment configuration only.

## Queue Checklist

- Use a worker-backed queue driver.
- Document worker counts and queue names.
- Document restart behavior after a release.
- Document failed job review and retry behavior.
- Review supervisor compatibility notes for the target server.

## Scheduler Checklist

- Add one server cron entry for Laravel scheduler execution.
- Review cleanup scheduling.
- Review health and monitoring scheduling.
- Confirm scheduler output is monitored without exposing sensitive values.

## Provider Checklist

- Confirm the selected provider is active.
- Confirm webhook readiness and signing secret readiness.
- Keep signing secrets outside reports and source code.
- Document provider rollback handling.

## Domain Checklist

- Confirm at least one active onboarded domain is ready.
- Review MX, SPF, DKIM, and DMARC checklists.
- Review provider mapping before traffic is enabled.
- Do not perform DNS automation from this checklist.

## Rollback Checklist

- Confirm rollback owners and rollback triggers.
- Confirm backup and restore prerequisites.
- Document queue restart handling during rollback.
- Pause traffic expansion when a blocker appears.
