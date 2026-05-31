# First Real Mail Validation

## Pre-Test Checklist

- Confirm the application is installed and the installer is locked.
- Confirm queue workers are running for the inbound queue.
- Confirm provider activation state is `active`.
- Confirm no provider secrets are pasted into tickets, logs, reports, or command output.

## Provider Checklist

- Provider is enabled.
- Webhook signing configuration is present through environment variables.
- Webhook route is registered for `/webhooks/mailgun`, `/webhooks/postmark`, or `/webhooks/ses`.
- Duplicate and replay protection remain enabled.

## Domain Checklist

- Domain exists in the domain inventory.
- Domain status is `active`.
- Onboarding state is `active`.
- MX, SPF, DKIM, DMARC, and provider mapping readiness were manually confirmed.

## Mailbox Generation Checklist

- Generate a mailbox on the onboarded domain.
- Confirm fallback domains remain configured.
- Confirm inactive and suspended onboarding domains are not assigned.
- Normalize mailbox addresses before using them in diagnostics.

## Webhook Delivery Checklist

- Send one manual test email from outside the platform.
- Confirm the provider webhook returns a queued response.
- Do not paste raw payloads or secret headers into reports.

## Queue Worker Checklist

- Confirm the inbound queue has capacity.
- Confirm the intake moves through queued and processed timestamps.
- Confirm duplicate provider deliveries do not create duplicate intakes.

## Inbox Visibility Checklist

- Confirm the message appears only in the current mailbox.
- Confirm expired, quarantined, and deleted messages stay hidden.
- Prefer sanitized HTML body and safe text fallback.

## Troubleshooting

- Run `mail:first-real-check` for readiness.
- Add `--intake`, `--message`, or `--mailbox` to trace a known item.
- Review blocker and warning counts before repeating the live test.
- Suspend the domain or provider if queue lag, webhook rejection, or inbox visibility regresses.
