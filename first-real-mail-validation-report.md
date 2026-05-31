# STEP41 First Real Mail Validation Report

## Readiness Strategy

STEP41 adds a safe first-real-mail validation workflow. It checks provider activation, webhook readiness, domain onboarding, queue capacity, mailbox generation, message visibility, and cleanup compatibility without external HTTP calls or live credential setup.

## Trace Strategy

`MailReceptionTraceService` traces provider intake to inbound intake, queued job handoff, email message storage, and public inbox visibility. Lookup is supported by intake UUID, provider message id, email message UUID, and mailbox address.

Trace output is intentionally safe. It does not include raw provider payloads, raw HTML bodies, secret headers, signature secrets, or storage paths.

## Blockers

Blockers include inactive providers, missing signing configuration, missing webhook routes, inactive domain onboarding, domain readiness blockers, queue readiness blockers, and mailbox generation failures.

## Warnings

Warnings include mailbox normalization issues, missing mailbox context before the first message is known, and cleanup settings that deserve operator review.

## Manual Steps

- Confirm provider and domain readiness.
- Generate a mailbox on the real onboarded domain.
- Send exactly one manual test email.
- Trace the intake or message using `mail:first-real-check`.
- Confirm the message is visible only in the expected mailbox.

## Next Production Validation Phase

After the first real message is validated safely, proceed to STEP42 Production Load & Stress Validation.
