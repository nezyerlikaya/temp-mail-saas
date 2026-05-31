# Production Provider Activation

## Validation Sequence

1. Run provider sandbox validation.
2. Run staging readiness validation.
3. Confirm installer and environment readiness.
4. Confirm webhook route readiness.
5. Confirm signing configuration exists in environment variables.
6. Confirm queue readiness and idempotency.
7. Mark provider `ready`.
8. Activate one provider at a time.

## Mailgun Checklist

- Signing secret configured in environment.
- `/webhooks/mailgun` registered and protected by install enforcement.
- Staging validation passed.
- Queue-first intake verified.
- Duplicate webhook handling verified.

## Postmark Checklist

- Webhook token configured in environment.
- `/webhooks/postmark` registered.
- Staging validation passed.
- Attachment metadata remains metadata-only.

## Amazon SES Checklist

- SES signing configuration present.
- `/webhooks/ses` registered.
- Message id, timestamp, source, and destination normalization verified.

## Rollback Guidance

- Transition provider to `suspended` if failures increase.
- Keep fallback providers or local intake available where possible.
- Keep queue workers running until backlog is drained.
- Do not remove historical intake or message data during rollback.

## Safety Checks

- Provider state must be valid.
- Staging validation must be acceptable.
- Webhook readiness must pass.
- Queue readiness must pass.
- Installer readiness must pass.
- Signing configuration should be present unless explicitly allowed by configuration.

## Command

```bash
php artisan provider:activation-status
```

The command prints state, blockers, warnings, and passed checks without exposing credentials.
