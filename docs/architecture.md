# Temp Mail SaaS Architecture

## Overview

Temp Mail SaaS v1 is prepared as a modular monolith. The application keeps one deployable Laravel codebase while organizing future capabilities into clear service, contract, DTO, event, policy, and configuration boundaries.

STEP01 contains no business features. It only creates the foundation that later steps can extend without changing public route structure or replacing core conventions.

STEP02 adds the central configuration and public health foundation. It still contains no Temp Mail business behavior.

## Folder Structure

- `app/Services`: Business workflow layer. Controllers should stay thin and delegate decisions here.
- `app/Services/Core`: Cross-module foundation services.
- `app/Services/Mail`: Reserved for mailbox and inbound mail workflows.
- `app/Services/Domain`: Reserved for domain inventory, routing, and verification workflows.
- `app/Services/User`: Reserved for account and user-facing workflows.
- `app/Services/Billing`: Reserved for plans, billing, and payment workflows.
- `app/Services/Abuse`: Reserved for rate limiting, reporting, and abuse controls.
- `app/Services/Seo`: Reserved for SEO metadata and public page strategy.
- `app/Services/System`: Reserved for operational and system-level services.
- `app/Contracts`: Interfaces for replaceable service boundaries.
- `app/DTOs`: Readonly data transfer objects for future service input/output.
- `app/Enums`: Backed enums for stable status, feature, and type values.
- `app/Actions`: Single-purpose application actions.
- `app/Policies`: Authorization policy location, ready for future authentication.
- `app/Events`, `app/Listeners`, `app/Jobs`: Event and queue-ready locations.
- `app/Support`: Shared helpers and traits. Helpers are namespaced to avoid global pollution.

## Service Pattern

Controllers should only validate requests, call a service or action, and return a response. Business rules belong in services. External integrations should be expressed through contracts before concrete adapters are added.

Base classes are intentionally light:

- `App\Services\Service` gives all services a common parent.
- `App\DTOs\DataTransferObject` provides readonly DTO serialization.
- `App\Actions\Action` reserves a consistent home for single-purpose tasks.

## Configuration Strategy

Configuration is split by concern:

- `config/tempmail.php`: Core app identity and architecture flags.
- `config/domains.php`: Future domain module settings.
- `config/inbound.php`: Future inbound mail adapter settings.
- `config/retention.php`: Future lifecycle and cleanup policies.
- `config/abuse.php`: Future abuse prevention settings.
- `config/features.php`: Feature flags that default to disabled.
- `config/seo.php`: Shared public page metadata.

Future modules should add configuration keys without removing or renaming existing ones.

## Route Strategy

Current public routes:

- `/`: Placeholder homepage.
- `/health`: JSON health response.
- `/status`: Public-safe status page.

Reserved route spaces:

- `/admin`: Reserved for a future admin module.
- `/api`: Reserved for future API endpoints.

The reserved routes remain unimplemented after STEP02.

## STEP02 Configuration Foundation

STEP02 introduces system services that future modules can depend on without reading raw environment variables throughout the codebase.

- `App\Services\System\AppConfigService`: Central typed access to public app name, support email placeholder, locale, fallback locale, timezone, default mailbox TTL, cleanup chunk size, inbound provider placeholder, and SEO defaults.
- `App\Services\System\FeatureFlagService`: Reads `config/features.php` using dot notation. Unknown flags return `false` and never throw exceptions.
- `App\Services\System\EnvironmentService`: Returns structured environment, app key presence, debug, cache, and storage availability information without exposing secret values.
- `App\Services\System\HealthCheckService`: Builds the public-safe health report used by `/health` and the public status summary used by `/status`.

Configuration remains split by concern and uses safe environment defaults. Future modules should add settings to the existing config files instead of introducing scattered `env()` calls inside services or controllers.

## STEP02 Public Surfaces

Current public routes:

- `/`: Homepage showing STEP01 and STEP02 readiness only.
- `/health`: JSON-only health response with safe status checks.
- `/status`: Public-safe status page with no internal environment details.
- `/up`: Laravel framework health route.

The health and status outputs must never expose credentials such as app keys, database passwords, mail passwords, provider tokens, or API keys.

## Installer Compatibility

The configuration services are designed for a future installer without adding one now:

- Missing or blank config values resolve to safe defaults.
- App key presence is reported as a boolean only.
- Storage and cache readiness are detectable without exposing paths beyond public-safe labels.
- Feature flags default to disabled unless explicitly enabled.

## Future Admin Compatibility

The `/admin` route space remains reserved and unimplemented. Future admin work should consume `AppConfigService`, `FeatureFlagService`, `EnvironmentService`, and `HealthCheckService` rather than duplicating configuration or health logic.

## STEP03 User Account Foundation

STEP03 adds schema and model foundations for future member authentication, premium plans, API access, avatars, sessions, and RBAC. It does not add login screens, registration, public profiles, staff authentication, roles, or billing behavior.

The original Laravel users migration remains unchanged. A follow-up migration safely adds nullable or defaulted account fields:

- Identity: username, display name, and public slug.
- Account state: status, last login time, and last seen time.
- Avatar metadata: disk, path, MIME type, size, hash, and update time.
- Preferences: locale and timezone.
- Security preparation: two-factor state and password change time.
- Compatibility fields: account tier and API access state.

Usernames and public slugs are nullable and uniquely indexed. Avatar images are never stored as database blobs.

## Username Strategy

`App\Services\User\UsernameService` normalizes usernames to lowercase, trims surrounding whitespace, converts spaces to dashes, and allows letters, numbers, dashes, and underscores. Usernames must be between 3 and 32 characters and may not use reserved platform names such as `admin`, `support`, `api`, `status`, or `health`.

The username service performs no database writes. Future registration and profile services may use its normalized slug suggestion before checking persistence-level uniqueness.

## Avatar Metadata Strategy

`App\Services\User\AvatarMetadataService` represents avatar metadata without storing binary image data. It provides a default SVG fallback and uses Laravel storage disks when avatar metadata exists. Upload validation, image processing, and storage lifecycle behavior remain out of scope for STEP03.

## Privacy-Safe Profiles

`App\DTOs\User\UserProfileData` provides a public-safe profile representation for future UI and API surfaces. It includes only the user ID, username, display name, public slug, avatar URL, account tier, and status. Email is intentionally excluded by default.

## Future Compatibility

- Authentication can be layered onto the existing Laravel `User` model without changing the public routes.
- RBAC can add role and permission tables later without placing a role column on users.
- Billing can add subscription models later without storing provider identifiers on users.
- API tokens can be introduced later without enabling API business endpoints in STEP03.

## Extension Strategy

Future steps should extend the foundation in small, compatible increments:

1. Add contracts before concrete adapters when behavior may vary by provider.
2. Add DTOs for service input and output when arrays would become unclear.
3. Add events for cross-module side effects.
4. Add queued jobs only when asynchronous processing is required.
5. Add policies and gates when authentication and RBAC are introduced.
6. Keep feature flags disabled until a module is ready for exposure.

## Roadmap Compatibility

The structure supports shared hosting first while preserving a path to VPS scaling:

- The app remains a single Laravel deployment.
- Queues can start with database or sync drivers and later move to Redis.
- Inbound mail can start with simple adapters and later move to provider webhooks or dedicated workers.
- Billing, RBAC, admin, and API modules can be added without breaking the homepage or health routes.
