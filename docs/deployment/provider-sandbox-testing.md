# Provider Sandbox Testing

## Purpose

Sandbox testing validates provider payload handling without live provider accounts, external HTTP calls, production secrets, DNS automation, or real customer data.

## Fixture Strategy

Fixtures live under `tests/Fixtures/mail-providers` and use deterministic example data:

- `mailgun-valid.json`
- `mailgun-invalid.json`
- `postmark-valid.json`
- `postmark-invalid.json`
- `ses-valid.json`
- `ses-invalid.json`

Fixtures use `example.test`, sandbox message ids, and placeholder signature tokens.

## Signature Simulation

The sandbox validator replaces fixture placeholders with test-safe values:

- Current sandbox timestamp.
- Deterministic sandbox signing keys from configuration.
- Valid or invalid signatures depending on fixture case.

The validator can also simulate expired timestamps and duplicate/replay behavior.

## Mailgun Checklist

- Validate valid fixture.
- Validate invalid fixture rejection.
- Confirm normalized mailbox, sender, subject, body, recipients, attachments, provider id, and intake key.
- Confirm sandbox mail flow reaches inbox visibility.

## Postmark Checklist

- Validate webhook token simulation.
- Validate invalid token rejection.
- Confirm attachment metadata only.
- Confirm queue-first intake flow.

## Amazon SES Checklist

- Validate SES message id and timestamp signature simulation.
- Validate invalid signature rejection.
- Confirm normalized source and destination mapping.

## Local And Staging Safety

- Keep `sandbox_payload_logging_enabled=false` unless debugging with strictly fake data.
- Do not use production secrets.
- Do not paste full payloads into logs or tickets.
- Run `mail:provider-sandbox-check --all` before live staging validation.
