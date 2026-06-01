# Revenue Activation

## Billing Readiness Checklist

- Run `system:revenue-status`.
- Confirm billing tables exist.
- Confirm card storage fields do not exist.
- Confirm provider identifiers remain hidden from serialized output.

## Subscription Checklist

- Confirm free, member, and premium plans are active.
- Confirm provider plan map is configured.
- Confirm activation, upgrade, downgrade, cancellation, and invoice review readiness.

## Webhook Checklist

- Confirm billing webhook route is registered.
- Confirm signing secret readiness through environment configuration.
- Confirm webhook events store payload hashes, not raw payloads.

## Incident Checklist

- Confirm webhook failure readiness.
- Confirm invoice failure readiness.
- Confirm subscription mismatch readiness.

## Rollback Checklist

- Confirm manual plan assignment fallback.
- Confirm payment incident rollback readiness.
- Keep rollback execution manual.

## First Customer Checklist

- Confirm customer creation readiness.
- Confirm subscription assignment readiness.
- Confirm plan transitions and cancellation handling.
- Do not enable real checkout in this step.
