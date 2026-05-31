# First 24 Hours

## Launch-Day Checklist

- Run `system:launch-monitoring-status`.
- Keep provider, domain, queue, inbox, billing, API, and operations owners available.
- Review active alerts and open incidents at a fixed cadence.

## Monitoring Checklist

- Confirm health checks are available.
- Confirm monitoring aggregation is enabled.
- Confirm alert and incident readiness.
- Keep reports free of credentials, raw payloads, and secrets.

## Queue Checklist

- Watch pending jobs.
- Watch failed jobs.
- Review retry behavior before expanding traffic.

## Provider Checklist

- Watch provider intake failures.
- Watch webhook rejections.
- Pause expansion if provider failure thresholds are reached.

## Incident Checklist

- Classify incidents by provider, queue, domain, inbox, billing, API, or operations.
- Escalate high and critical incidents to category owners.
- Review rollback recommendations for critical incidents.

## Rollback Checklist

- Review rollback trigger state.
- Rollback is recommended when critical incident, queue, provider, or inbox thresholds are reached.
- This readiness layer does not execute rollback.

## Operator Guidance

- Treat `healthy` as continue monitoring.
- Treat `warning` as hold expansion and assign owners.
- Treat `critical` as launch commander review and rollback assessment.
