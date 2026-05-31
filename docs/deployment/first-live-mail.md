# First Live Mail Reception

## Provider Checklist

- Run `provider:live-readiness`.
- Confirm selected provider state is `active`.
- Confirm signing secret readiness without copying secret values into reports.

## Domain Checklist

- Run `domain:live-readiness`.
- Confirm active onboarded production domain readiness.
- Confirm fallback domain and suspension ownership.

## Webhook Checklist

- Confirm provider webhook route registration.
- Confirm signature verification.
- Confirm replay protection and duplicate protection.
- Confirm queue-first handoff.

## Queue Checklist

- Confirm intake and processing queues.
- Confirm cleanup and automation queue names.
- Confirm retry safety and worker-backed queue configuration.

## Inbox Checklist

- Confirm mailbox normalization.
- Confirm mailbox isolation.
- Confirm sanitized rendering.
- Confirm expired and quarantined messages remain excluded.

## Rollback Checklist

- Confirm provider fallback readiness.
- Confirm domain fallback readiness.
- Confirm suspension paths.
- Pause traffic expansion if the first trace is blocked.

## Validation Sequence

1. Run `mail:first-live-status` before traffic to review pending trace readiness.
2. Receive the first message manually through the provider workflow.
3. Run `mail:first-live-status` with safe trace identifiers.
4. Confirm intake accepted, queued, processed, stored, and inbox visible.
5. Proceed to STEP50 first 24-hour monitoring.
