# Incident Response Runbook

## Lifecycle

Incidents move through:

- `open`
- `acknowledged`
- `resolved`

## Triage

1. Identify category, severity, and affected surface.
2. Confirm whether an active monitoring alert already exists.
3. Assign severity using customer impact, security impact, and recovery time.
4. Keep metadata privacy-safe.

## Communication

- Keep internal notes factual and timestamped.
- Do not include secrets, tokens, raw payloads, passwords, or card data.
- Document user-facing impact separately from internal technical details.

## Resolution

Resolve only when the trigger has stopped and the affected service has recovered. Add follow-up recommendations to the release or stabilization backlog.
