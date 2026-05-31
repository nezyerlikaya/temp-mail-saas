# Live Provider Activation

## Provider Activation Checklist

- Run `provider:live-readiness`.
- Confirm Mailgun, Postmark, and Amazon SES configuration readiness.
- Confirm provider activation state is `active` before accepting real traffic.
- Keep all provider credentials in environment configuration only.

## Webhook Checklist

- Confirm `/webhooks/mailgun`, `/webhooks/postmark`, and `/webhooks/ses` routes are registered.
- Confirm installer enforcement is attached to webhook routes.
- Confirm signature verification is configured.
- Confirm replay-sensitive timestamp checks remain enabled.
- Confirm duplicate protection uses provider message ids and intake keys.
- Confirm webhook intake hands off to the queue.

## Signing Secret Checklist

- Configure signing secrets outside source control.
- Never copy signing secret values into reports, tickets, or audit metadata.
- Rotate secrets only through provider and environment workflows.

## Rollback Checklist

- Confirm fallback provider readiness.
- Confirm provider suspension readiness.
- Confirm queue drain guidance is documented.
- Confirm rollback ownership before enabling live traffic.

## Validation Checklist

- Confirm active onboarded domains exist.
- Confirm provider-domain mapping is valid.
- Confirm mailbox generation can use eligible domains.
- Confirm operations events and provider metrics are enabled.
