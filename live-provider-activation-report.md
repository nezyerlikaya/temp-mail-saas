# Live Provider Activation Report

## Readiness Summary

Run `php artisan provider:live-readiness` before enabling real inbound provider traffic. The command is audit-only and does not call provider APIs.

## Blockers

- Provider inactive state.
- Missing provider enablement.
- Missing signing secret readiness.
- Missing webhook route or installer enforcement.
- Worker-backed queue not configured.
- No active onboarded domain.
- Rollback or suspension readiness missing.

## Warnings

- Missing provider-domain mapping.
- Missing queue drain documentation.
- Missing observability confirmation.

## Recommendations

- Clear blockers before provider activation.
- Review warnings with operations ownership.
- Keep provider secrets out of reports and audit metadata.

## Rollback Strategy

- Keep fallback provider available.
- Keep suspension path ready.
- Drain or pause queues before provider rollback if intake degrades.
- Re-run readiness after rollback-affecting configuration changes.

## Activation Sequence

1. Confirm deployment readiness.
2. Confirm live provider readiness.
3. Confirm active domains and provider mapping.
4. Confirm webhook signing and queue-first handoff.
5. Confirm rollback ownership.
6. Proceed to STEP48 real domain activation readiness.
