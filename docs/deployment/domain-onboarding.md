# Domain Onboarding Checklist

## Onboarding Flow

1. Add the domain to inventory in the `draft` onboarding state.
2. Start configuration-only validation to move the domain to `validating`.
3. Confirm the DNS and provider mapping checklist manually.
4. Review blockers, warnings, and recommendations with `domain:onboarding-status`.
5. Move the domain to `ready`, then activate it only after operational review.
6. Suspend the domain immediately if production safety signals regress.

Only domains with both general status `active` and onboarding state `active` may be assigned from the domain pool.

## MX Checklist

- Confirm the intended inbound provider.
- Confirm the provider-specific MX target outside the application.
- Confirm the fallback domain remains available during rollout.

## SPF Checklist

- Confirm the sending policy is intentionally scoped.
- Review existing records before any manual DNS change.
- Do not paste DNS values into application logs or audit metadata.

## DKIM Checklist

- Confirm provider-side DKIM readiness.
- Store private material only with the provider or deployment secret tooling.
- Do not store keys in the application database.

## DMARC Checklist

- Review the rollout policy before activation.
- Confirm reporting destinations through the operational process.
- Keep policy changes separate from application deployment.

## Provider Mapping Checklist

- Map the domain to an enabled inbound provider.
- Confirm provider activation state is `ready` or `active`.
- Confirm queue-first intake and duplicate protection remain active.

## Rollback Guidance

- Suspend onboarding before changing provider or DNS configuration.
- Keep the previous working fallback domain active.
- Review queue lag, webhook failures, and inbox visibility.
- Roll back DNS changes manually through the approved operational process.
