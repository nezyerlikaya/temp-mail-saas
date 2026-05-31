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

## STEP04 Authentication Foundation

STEP04 adds Laravel-native normal-user account access. The existing `/`, `/health`, `/status`, and `/up` routes remain unchanged. The `/admin` and `/api` spaces remain reserved and unimplemented.

Normal-user routes now include:

- `/login` and `/logout`
- `/register`
- `/forgot-password` and `/reset-password/{token}`
- `/verify-email` and `/email/verification-notification`
- `/confirm-password`
- `/dashboard`

The dashboard is an authenticated placeholder only. It shows a greeting, account tier, account status, and email verification state. It deliberately contains no mailbox, billing, staff, or admin links.

## Account Access Flow

Registration uses `App\Services\User\UsernameService` when an optional username is supplied. Usernames are normalized before validation, checked against reserved names, and stored as the initial public slug. New accounts start with the free tier, active status, API access disabled, two-factor authentication disabled, and a recorded password change timestamp.

Registration also includes a lightweight bot-prevention foundation: a hidden honeypot field and a configurable minimum submit duration. External CAPTCHA services remain out of scope.

## Login Security

Login uses Laravel's session guard, password hashing, and rate limiter. Successful login regenerates the session and updates last-login and last-seen timestamps. Failed authentication returns a generic message. Suspended and inactive accounts are constrained out of authentication without revealing account state.

Logout invalidates the current session and regenerates the CSRF token.

## Email Verification And Password Reset

The `User` model implements Laravel's email verification contract. Verification notice, signed verification, and resend routes are ready for local-safe mail drivers and future SMTP configuration.

Password reset uses Laravel's broker and token table. Forgot-password responses use the same public message whether an account exists or not, reducing account enumeration risk.

## Admin And Staff Exclusion

STEP04 authenticates normal users only. Admin panels, staff guards, staff login routes, roles, permissions, and RBAC are intentionally deferred so future authorization design can be added without mixing trust levels into the member access flow.

## STEP05 Staff And RBAC Foundation

STEP05 prepares staff accounts and role-based access control for a future admin area. It does not create a real admin dashboard, staff login UI, staff password reset, role management screens, or admin CRUD modules.

Staff users are stored separately from normal users in `staff_users`. This keeps customer/member identity isolated from operational access and avoids mixing trust levels in the same authentication model. Normal users continue to use the `web` guard and `users` provider. Staff access is prepared through a separate `staff` guard and `staff_users` provider.

## RBAC Design

RBAC uses first-party Laravel models and migrations without an external package dependency:

- `App\Models\StaffUser`: Staff authenticatable model with `roles()`, `hasRole()`, `hasPermission()`, and `isActive()`.
- `App\Models\Role`: System or future custom role with many permissions and many staff users.
- `App\Models\Permission`: Permission slug grouped by operational area.
- `permission_role`: Role-permission pivot.
- `role_staff_user`: Staff-role pivot.

Pivot tables use composite unique indexes to prevent duplicate assignments. Foreign keys cascade on delete so orphaned pivot records do not remain if a role, permission, or staff user is removed.

## Permission Slug Strategy

Permission slugs are centralized in `config/permissions.php`. Slugs follow an `area.action` format, such as:

- `users.view`
- `users.suspend`
- `staff.manage`
- `system.manage`
- `mail.quarantine`
- `domains.manage`
- `abuse.manage`
- `settings.manage`

The initial map creates stable names for future admin modules without building those modules yet.

## Role Strategy

System roles are seeded through `PermissionSeeder` and `RoleSeeder`:

- `super_admin`: receives all permissions.
- `admin`: broad operational access without owner-only settings management.
- `support`: support-safe read and triage permissions.
- `moderator`: content, abuse, and quarantine-oriented permissions.

`StaffUserSeeder` is local/testing safe only. It creates a staff user only when local/testing environment variables are provided, and no production credentials are hardcoded.

## Admin Route Reservation

`/admin` is reserved as `admin.index` and currently returns `403`. This keeps the route name stable for STEP06+ while making it clear that the admin area is not implemented yet.

Middleware placeholders are registered as:

- `staff.active`
- `staff.permission`

They are available for future admin routes but are not applied broadly in STEP05.

## Staff Security Boundary

Staff authorization gates are prepared around `StaffUser::hasPermission()` and active staff status. Normal user authentication from STEP04 remains separate and unaffected. This separation keeps member account access, future staff access, and future RBAC policy work independently extensible.

## STEP06 Languages And Translation Foundation

STEP06 adds localization infrastructure without creating a language admin module, translation editor, import/export flow, localized route prefixes, or SEO hreflang management.

Languages are stored in `languages` with:

- `code`: Unique locale code such as `en`, `tr`, or future regional variants.
- `name` and `native_name`: Display labels for UI.
- `direction`: `ltr` or `rtl`, cast through `LanguageDirection`.
- `is_active`: Whether the locale can be selected at runtime.
- `is_default`: Logical default locale. The model ensures only one default language remains active as default.
- `sort_order`: Stable display ordering.

Translations are stored in `translations` as individual rows, not JSON blobs. Each translation belongs to a language and has `group`, `key`, nullable `value`, and `is_custom`. The unique key is `language_id + group + key`, which keeps the schema compatible with future admin editing and missing-translation tracking.

## Locale Detection Strategy

`App\Services\System\LocaleService` determines locale in this order:

1. Authenticated user preference when valid.
2. Session locale when valid.
3. Request locale when valid.
4. Configured default locale.
5. Configured fallback locale.

The service validates requested locales against active language rows when the table exists. If the database is unavailable during early install or testing, it falls back to configured locales from `config/tempmail.php`.

`SetLocale` middleware applies the resolved locale for web requests and sets Carbon's locale when possible. Errors are swallowed intentionally so health checks and early install states do not break.

## Translation Fallback Strategy

`App\Services\System\TranslationService` resolves text in this order:

1. Database translation for the requested locale.
2. Database translation for the fallback locale.
3. Laravel language file entry.
4. Explicit default text passed by the caller.
5. The `group.key` string.

This structure is cache-ready but avoids cache invalidation complexity until an admin translation editor exists.

## Locale Switching

`POST /locale` is registered as `locale.switch`. It accepts a locale code, validates it through `LocaleService`, stores it in the session, and redirects back using Laravel's safe back redirect behavior. It does not require authentication and does not introduce localized route prefixes.

A minimal Blade component, `resources/views/components/locale-switcher.blade.php`, provides a CSRF-protected language selector for active locales.

## Future Localization Compatibility

The schema is ready for future RTL languages, admin-created custom translations, missing translation tracking, and translation editor screens. STEP06 intentionally excludes localization CRUD, editor UI, import/export, automatic machine translation, translation progress dashboards, and SEO hreflang management so future admin localization work can build on stable service and schema boundaries.

## STEP07 Media Library Foundation

STEP07 adds a reusable media metadata layer for future avatars, blog images, SEO images, content media, system assets, mailbox attachments, and integrations. It does not implement upload screens, media manager UI, image editing, thumbnails, conversions, downloads, blog integration, or mailbox attachments.

The `media` table stores metadata only:

- Stable identity: `uuid`.
- Storage metadata: disk, directory, filename, storage driver, and storage path.
- File metadata: original filename, extension, MIME type, size, checksum, visibility, and status.
- Image metadata: optional width and height.
- Ownership metadata: optional normal user or staff uploader.

No file blobs or binary content are stored in the database.

## Media Storage Strategy

`config/media.php` centralizes media defaults. Local storage works by default through Laravel's filesystem configuration. Public and S3-compatible disks remain supported through existing filesystem disks without making S3 required.

Path generation is centralized in `App\Services\Media\MediaService` and follows a collection/year/month strategy:

- `avatars/YYYY/MM`
- `blog/YYYY/MM`
- `seo/YYYY/MM`
- `content/YYYY/MM`
- `system/YYYY/MM`
- `attachments/YYYY/MM`

Visibility defaults to private, with public defaults reserved for safe public collections such as blog, SEO, and system assets.

## Media Service And DTO

`App\Services\Media\MediaService` creates media records, generates UUIDs, builds safe storage paths, determines visibility, and validates required metadata. It does not handle uploaded files or manipulate images.

`App\DTOs\Media\MediaData` exposes only public-safe metadata: UUID, filename, MIME type, size, and visibility. Internal disks, directories, storage paths, and checksums are intentionally excluded.

## Future Processing Pipeline

`App\Contracts\Media\MediaProcessorContract` reserves a stable processing boundary for future image optimization, thumbnail generation, WebP/AVIF conversion, virus scanning, or attachment processing. No processors are implemented in STEP07.

## CDN And Attachment Compatibility

The media foundation is CDN-ready because storage paths, visibility, disk, and storage driver are tracked independently from public DTO output. Future CDN URL generation can be added behind services without changing the media schema.

Mailbox attachments can later reuse the same metadata table while keeping attachment downloads, authorization, quarantine, and retention policies in dedicated future modules.

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
