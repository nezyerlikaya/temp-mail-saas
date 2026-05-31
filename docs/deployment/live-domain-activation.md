# Live Domain Activation

## Domain Activation Checklist

- Run `domain:live-readiness`.
- Confirm the domain is active and onboarding state is `active`.
- Confirm provider mapping uses a live-ready provider.
- Keep registrar credentials and DNS credentials outside the application.

## MX Checklist

- Confirm inbound MX readiness manually.
- Confirm provider mapping before enabling traffic.
- Do not store DNS record values in readiness reports.

## SPF Checklist

- Confirm SPF readiness manually.
- Review provider guidance outside the application.

## DKIM Checklist

- Confirm DKIM readiness manually.
- Keep private keys and signing secrets outside reports and source code.

## DMARC Checklist

- Confirm DMARC readiness manually.
- Review policy changes with operations ownership.

## Rollback Checklist

- Confirm fallback domain readiness.
- Confirm domain suspension path.
- Confirm mailbox generation remains safe during rollback.
- Re-run readiness after any rollback-affecting configuration change.

## Validation Sequence

1. Confirm onboarding readiness.
2. Confirm MX, SPF, DKIM, DMARC, and provider mapping readiness.
3. Confirm domain pool selection and suspended-domain exclusion.
4. Confirm mailbox generation and normalization.
5. Confirm rollback and observability readiness.
