# Provider Failures Runbook

## Signals

- `provider_failures`, `provider_rejections`, or `provider_throughput` alerts.
- Recent operations events from `inbound-provider`.
- Increased rejected webhooks or invalid signatures.

## First Checks

1. Verify the provider is enabled in configuration.
2. Confirm webhook signing secrets are configured correctly.
3. Check recent operations events by provider name.
4. Confirm queue backlog is not preventing intake processing.

## Safe Actions

- Temporarily disable a failing provider if another provider or local fallback is available.
- Rotate provider webhook secrets only through secure deployment procedures.
- Ask the provider dashboard for webhook delivery status; do not store raw payloads in the app.

## Escalation

Escalate as critical if all enabled providers reject or fail intake.
