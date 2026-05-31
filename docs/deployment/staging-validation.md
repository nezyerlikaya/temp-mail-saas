# Live Provider Staging Validation

## Purpose

Staging validation prepares real provider sandbox or staging accounts without production domains, production traffic, DNS automation, or provider billing activation.

## Installer Validation Checklist

- Confirm installation is complete.
- Confirm `install.lock` exists.
- Confirm `APP_KEY` is configured.
- Confirm `/login`, `/register`, `/dashboard`, `/admin`, `/admin/login`, `/api/*`, `/billing/*`, and `/inbox` are blocked until installation is healthy.

## Webhook Validation Checklist

- Confirm `/webhooks/mailgun` is registered.
- Confirm `/webhooks/postmark` is registered.
- Confirm `/webhooks/ses` is registered.
- Confirm disabled providers return safe rejection responses.
- Confirm invalid signatures are rejected.
- Confirm duplicate webhooks do not duplicate intake or storage.

## Mailgun Staging Checklist

- Configure staging signing secret in environment variables.
- Enable Mailgun inbound only in staging.
- Use a staging domain or provider sandbox recipient.
- Validate webhook signature and queue-first intake.

## Postmark Staging Checklist

- Configure staging webhook token.
- Enable Postmark inbound only in staging.
- Validate token rejection and accepted test delivery.

## SES Staging Checklist

- Configure staging signing settings.
- Enable SES inbound only in staging.
- Validate message id, timestamp, destination mapping, and duplicate protection.

## Queue Validation Checklist

- Confirm inbound queue name.
- Confirm queue driver choice.
- Confirm failed jobs review process.
- Confirm retry/idempotency behavior before sending repeated provider tests.

## Domain Validation Checklist

- Configure staging allowed domains.
- Keep fallback domains available.
- Do not require production domains for staging validation.

## Command

Run:

```bash
php artisan system:staging-readiness
```

The command prints only safe blocker and warning summaries.
