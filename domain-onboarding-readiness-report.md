# STEP40 Domain Onboarding Readiness Report

## Onboarding Readiness

STEP40 adds a configuration-only real domain onboarding framework. It does not perform DNS lookups, registrar operations, DNS automation, nameserver changes, or provider API calls.

Supported onboarding states:

- `draft`
- `validating`
- `ready`
- `active`
- `suspended`

## Blockers

Activation blockers include incomplete manual DNS readiness confirmation, incompatible provider mapping, low domain health, suspended domains, incompatible feature gates, and inactive organizations.

## Warnings

Test domains are warned by default so they cannot be confused with production-ready domains during operational review.

## Recommendations

- Confirm MX, SPF, DKIM, DMARC, and provider mapping readiness manually.
- Activate one domain at a time.
- Keep fallback domains available during rollout.
- Monitor queue lag, webhook failures, and inbox visibility after activation.
- Suspend onboarding before manual rollback actions.

## Next Activation Steps

Use `domain:onboarding-status` for a safe readiness summary. After manual readiness confirmation, validate the first real mail reception flow in STEP41.
