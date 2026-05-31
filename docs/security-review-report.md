# Security Review Report

Project: Temp Mail SaaS v1  
Phase: STEP29 Security Review & Hardening  
Status: Ready for STEP30 Production Release Candidate

## Review Summary

STEP29 reviewed authorization, RBAC, mass assignment, sensitive data exposure, webhook security, API key handling, sessions, CSRF boundaries, XSS risk, configuration posture, and logging behavior.

No architecture rewrites were required. Hardening changes were additive and focused on defense in depth.

## Findings

### Sensitive Model Serialization

Some internal hashes, secrets, storage paths, raw HTML fields, and provider identifiers were available through default Eloquent serialization. Existing DTOs and UI paths were already careful, but hiding sensitive fields at the model layer provides safer defaults for future modules.

Severity: Medium  
Status: Fixed

### Billing Webhook Replay Without Event ID

Billing webhook idempotency used provider event IDs when available. If a future provider sends a signed payload without an event ID, replayed payloads could create repeated event rows.

Severity: Medium  
Status: Fixed

### Security Headers

Web responses did not add explicit browser hardening headers.

Severity: Low  
Status: Fixed

## Fixes Applied

### Default Serialization Hardening

Added hidden model attributes for sensitive/internal fields:

- API key hashes
- Outbound webhook secret hashes
- Encrypted integration configuration
- Email attachment checksums and storage paths
- Raw email HTML bodies and intake keys
- Media checksums and storage paths
- Billing provider customer, subscription, and invoice IDs

This does not change persistence or service behavior. It only makes accidental serialization safer.

### Billing Webhook Replay Hardening

Billing webhooks now deduplicate by provider, event type, and payload hash when a provider event ID is missing.

This preserves the existing event-ID idempotency path and adds replay protection for provider payloads that do not include IDs.

### Web Security Headers

Added a lightweight `SecurityHeaders` middleware to web responses:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`

No CSP was added in this phase to avoid breaking existing Blade/Alpine/Tailwind behavior without a dedicated browser QA pass.

## Authorization And RBAC Review

Admin routes remain protected by:

- `staff.active`
- route-specific `staff.permission:*` middleware

Tests cover permission bypass attempts against localization write routes. The existing RBAC model remains server-side enforced and does not rely on hidden UI controls.

Recommendation:

Every future admin route should continue to require both active staff and a specific permission.

## Mass Assignment Review

Models use explicit fillable lists. Security tests confirm user mass assignment cannot elevate:

- status
- account tier
- API access

Operational/service-owned models still expose fillable internal attributes because they are not public request-bound resources. Future controllers should continue to validate input and pass only explicit fields into services.

## Sensitive Data Review

Verified and hardened:

- API raw keys are never stored.
- API key hashes are hidden from serialization.
- Webhook secrets are hashed and hidden from serialization.
- Integration configuration is encrypted and hidden from serialization.
- Billing metadata sanitization removes card/payment/secret/token keys.
- Automation execution metadata strips payload/raw/secret/token/password-like keys.
- Operations logging strips sensitive metadata.
- Public inbox DTOs do not expose internal IDs, raw storage paths, or raw HTML bodies.

## Webhook Security Review

Billing webhooks:

- Require provider signature validation.
- Store payload hashes only.
- Do not store raw payloads.
- Are idempotent by event ID.
- Are now idempotent by payload hash when event ID is absent.
- Fail malformed signed payloads safely.

Future integration webhooks:

- Store secret hashes only.
- Store delivery payload hashes only.
- Do not perform external delivery yet.

Residual risk:

Live provider adapters still need provider-specific replay windows, timestamp validation, and signature-header tests before production billing is enabled.

## API Security Review

API access:

- Uses hashed API keys.
- Rejects malformed, missing, revoked, and expired keys.
- Enforces plan-aware API access.
- Enforces plan-aware rate limits.
- Logs usage without request body storage.
- Does not expose raw keys in API responses.

Residual risk:

Future API business endpoints must stay under the `api.key` middleware and avoid logging request payloads.

## Session Security Review

Verified:

- User login regenerates the session.
- Logout invalidates the session and regenerates the CSRF token.
- Suspended users cannot log in.
- Tenant context validates active organization membership before use.
- Session serialization is JSON, avoiding PHP object session serialization.

Recommendation:

Enable secure cookies in production via `SESSION_SECURE_COOKIE=true` once HTTPS is configured.

## CSRF Review

CSRF exceptions are limited to:

- `billing/webhooks/*`

Installer, localization, locale switching, inbox mutation, auth, and dashboard routes remain inside the web middleware group and are not configured as CSRF exceptions.

## XSS Review

Blade templates use escaped output. No raw `{!! !!}` rendering was found.

Security tests confirm localization values containing script tags render escaped in admin pages.

Public inbox JSON exposes sanitized HTML only and does not expose raw `html_body`.

Recommendation:

If future content/blog rendering needs trusted HTML, it should use a dedicated sanitizer pipeline and explicit allowlist.

## Configuration Review

Production-oriented defaults are in place:

- `APP_ENV` defaults to production.
- `APP_DEBUG` defaults to false.
- Session serialization is JSON.
- Automation schedules are disabled by default.
- External AI, OAuth, and marketplace UI are disabled by default.

Recommendation:

Before production release, validate `.env` values for `APP_KEY`, `APP_URL`, mail transport, queue driver, session secure cookies, and log level.

## Logging Review

Reviewed logging foundations:

- API usage logs avoid payloads.
- Billing webhook events store payload hashes only.
- Operations events sanitize sensitive metadata.
- Automation execution metadata excludes raw payload and secret-like fields.
- Abuse logging stores hashes rather than raw IP/session/user-agent values.

## Security Tests Added

Added `tests/Feature/SecurityHardeningTest.php` covering:

- Security headers
- Admin permission bypass rejection
- CSRF exception scope
- Billing webhook CSRF exception behavior
- API key hash serialization hiding
- Webhook secret hash serialization hiding
- Encrypted integration configuration serialization hiding
- Storage path/hash serialization hiding
- Raw email HTML serialization hiding
- Billing webhook replay without event ID
- XSS-safe localization rendering
- User mass-assignment escalation protection
- API key exposure prevention in API responses and usage logs

## Verification Result

Full test suite result:

```text
250 passed, 1094 assertions
```

## Residual Risks

1. A dedicated staff login UI remains future work.
2. Live billing adapters require provider-specific timestamp/replay validation.
3. A strict Content Security Policy should be added after browser QA confirms compatible script/style strategy.
4. External connector implementations must be separately reviewed before enabling real providers.
5. Attachment download endpoints are still future work and must enforce mailbox/session authorization.

## Recommendations

1. Keep all future admin routes behind `staff.active` and route-specific permissions.
2. Keep raw payload storage disabled across billing, webhooks, operations, API, and automation.
3. Add CSP in a dedicated hardening step after UI QA.
4. Add provider-specific webhook timestamp windows before real billing providers go live.
5. Require security tests for every future endpoint that mutates state.

## Hardening Status

Security posture after STEP29:

```text
Ready for STEP30 Production Release Candidate
```
