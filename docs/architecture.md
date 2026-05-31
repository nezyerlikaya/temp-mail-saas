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
