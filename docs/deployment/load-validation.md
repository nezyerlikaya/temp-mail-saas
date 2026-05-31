# Production Load Validation

## Queue Readiness Checklist

- Confirm queue connection and inbound queue name.
- Review pending and failed job thresholds.
- Confirm queue-first intake for provider webhooks.
- Confirm duplicate protection and idempotent intake processing.

## Inbox Readiness Checklist

- Confirm mailbox generation limits.
- Confirm polling rate limits.
- Confirm message retrieval limits.
- Confirm expired, quarantined, and deleted messages remain hidden.

## Provider Readiness Checklist

- Confirm provider registry entries.
- Confirm provider activation framework is configured.
- Confirm replay protection through signature verification.
- Confirm provider message ids and intake keys protect duplicate delivery.

## Domain Pool Readiness Checklist

- Confirm active onboarding domains are eligible.
- Confirm inactive and suspended onboarding domains are excluded.
- Confirm fallback domains remain configured.
- Confirm assignment history is available for operational review.

## Monitoring Readiness Checklist

- Confirm monitoring is enabled.
- Confirm operations metrics can be collected.
- Review active alerts and critical incidents.
- Keep dashboard queries paginated and aggregate-heavy views cached.

## Scaling Guidance

- Use `system:load-readiness` for safe local readiness review.
- Treat documented load scenarios as checklists, not traffic generators.
- Do not run external load tests until operators approve queue, database, provider, and hosting limits.
- On shared hosting, prefer conservative polling intervals, small cleanup chunks, and visible queue backlog thresholds.
