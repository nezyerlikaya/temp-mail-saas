# Queue Failures Runbook

## Signals

- `monitoring_alerts` contains `queue_lag` or `failed_job_spike`.
- `queue_metrics.pending_jobs` grows across review windows.
- `queue_metrics.failed_jobs` is greater than the configured threshold.

## First Checks

1. Confirm the configured queue connection.
2. Confirm the queue worker or shared-host scheduled worker is running.
3. Review recent failed jobs without exposing payloads.
4. Check whether inbound mail, cleanup, billing, or automation jobs are the source.

## Safe Actions

- Restart queue workers if the hosting environment supports it.
- Reduce intake rate at the provider if backlog keeps growing.
- Increase worker count on VPS deployments.
- Keep failed job payloads private and do not paste them into tickets.

## Escalation

Escalate as high severity when user-facing inbox delivery is delayed. Escalate as critical when inbound processing is stopped and backlog continues to grow.
