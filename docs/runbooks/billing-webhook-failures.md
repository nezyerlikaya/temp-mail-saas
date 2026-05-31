# Billing Webhook Failures Runbook

## Signals

- `billing_webhook_failures` monitoring alert.
- Billing webhook events with `failed` or `rejected` status.
- Subscription sync issues reported by customers or staff.

## First Checks

1. Confirm billing provider webhook secret configuration.
2. Check event ids for duplicate or replay attempts.
3. Review sanitized error messages only.
4. Confirm no card data or raw payment payloads are stored.

## Safe Actions

- Re-run provider-side webhook delivery only after signature configuration is verified.
- Keep billing events idempotent.
- Avoid manual subscription changes unless the customer mapping is confirmed.

## Escalation

Escalate as high when plan access is out of sync. Escalate as critical if all billing webhooks are failing.
