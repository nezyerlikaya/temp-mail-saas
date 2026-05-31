# STEP36 First Live Validation Report

## Checks Added

STEP36 adds production environment validation, server readiness validation, first-live smoke validation, and the `system:first-live-check` command.

## Validation Strategy

Validation is service-level and local to the application. It does not call external URLs, deploy code, provision infrastructure, create DNS records, or configure provider accounts.

The command summarizes:

- Environment blockers and warnings.
- Server readiness blockers and warnings.
- First-live smoke blockers and warnings.

## Remaining Manual Deployment Steps

- Configure production `.env`.
- Run migrations.
- Confirm installer completion and install lock.
- Confirm queue worker or scheduled queue strategy.
- Confirm scheduler cron where supported.
- Confirm provider/domain onboarding after live provider setup.

## Launch Blockers

Typical blockers include missing `APP_KEY`, enabled debug mode, failed database connectivity, unwritable storage paths, missing installer lock, missing required PHP extensions, or missing protected core routes.

## Recommendations

- Run `system:first-live-check` after installation and after each deployment.
- Keep provider traffic low until queue behavior is observed.
- Keep fallback domains active during provider onboarding.
- Keep rollback archive and database backup until post-deploy checks pass.
