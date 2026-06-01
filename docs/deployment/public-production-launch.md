# Public Production Launch

## Launch Checklist

- Run `system:public-launch-status`.
- Confirm public launch status is `READY`.
- Confirm launch operations certification is `CERTIFIED`.
- Keep rollout expansion manual.

## Rollout Checklist

- Confirm controlled rollout notes.
- Confirm rollback owner.
- Confirm support coverage.
- Expand traffic only after reviewing warnings.

## Provider Checklist

- Confirm live provider readiness.
- Confirm signed webhook readiness.
- Keep provider credentials outside reports.

## Domain Checklist

- Confirm live domain readiness.
- Confirm fallback domain readiness.
- Keep DNS and registrar credentials outside reports.

## Monitoring Checklist

- Review health, queue, provider, inbox, billing, API, abuse, and operations signals.
- Review launch-day incidents.
- Review rollback triggers.

## Rollback Checklist

- Treat `rollback-recommended` as a launch hold.
- Keep rollback execution manual.
- Record decisions without raw payloads or secrets.

## First-Week Operations Checklist

- Review signals at configured daily checkpoints.
- Review critical incidents and provider failures first.
- Review support queue and rollback triggers at each checkpoint.
