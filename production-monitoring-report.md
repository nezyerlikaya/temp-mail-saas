# STEP34 Production Monitoring Report

## Monitoring Architecture

STEP34 adds vendor-neutral monitoring foundations for Temp Mail SaaS v1. The system uses existing health checks, queue metrics, provider operations events, API usage logs, and billing webhook events as monitoring signals.

No external monitoring vendors are required. The foundation remains compatible with shared hosting and can later feed external alerting tools.

## Incident Lifecycle

Incidents are tracked in the `incidents` table with category, severity, status, title, summary, detected timestamp, optional resolution timestamp, and privacy-safe metadata.

Lifecycle:

- `open`
- `acknowledged`
- `resolved`

Critical monitoring alerts can create incidents automatically when enabled by configuration.

## Alert Lifecycle

Monitoring alerts are tracked in `monitoring_alerts` with source, alert type, severity, status, message, and lifecycle timestamps.

Lifecycle:

- `active`
- `acknowledged`
- `resolved`

Active alert deduplication is enabled by default to avoid alert floods during repeated monitoring reviews.

## Monitoring Coverage

The monitoring service evaluates:

- Queue lag and failed job spikes.
- Provider failures, rejections, and throughput.
- API usage spikes and API failures.
- Billing webhook failures and rejected billing events.

`monitoring:health-review` summarizes alert and incident state. `monitoring:incident-review` summarizes open and critical incidents.

## Uptime Readiness

`UptimeReadinessService` checks health route readiness, status route readiness, incident tracking, alert tracking, and operations event storage.

This prepares the app for uptime checks without requiring PagerDuty, Datadog, Prometheus, Grafana, Slack, email alerts, or any external service.

## Runbook Strategy

Operational runbooks live in `docs/runbooks`:

- Queue failures.
- Provider failures.
- Billing webhook failures.
- Incident response.

The runbooks focus on privacy-safe diagnosis, shared-hosting first recovery, and VPS scaling paths.

## Remaining Recommendations

- Add external uptime checks after deployment.
- Add queue worker supervision on VPS deployments.
- Review slow query logs and queue lag after real provider traffic begins.
- Add human notification integrations only after the internal alert lifecycle is stable.
