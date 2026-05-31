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

## STEP08 Content Foundation

STEP08 adds metadata and service foundations for future pages, posts, announcements, help center content, SEO content, and marketing pages. It does not implement an editor, admin content screens, blog frontend, media picker, revision history, comments, search, or content API.

The `contents` table stores:

- Identity: integer ID and UUID.
- Content fields: title, slug, excerpt, and body content.
- Classification: content type and status.
- Publishing metadata: nullable `published_at`.
- Staff author relationship through `author_staff_id`.
- SEO fields: meta title and meta description.
- Media compatibility through nullable `featured_media_id`.
- Localization compatibility through nullable `locale`.

Indexes are prepared for slug, type, status, and locale.

## Content Slug Strategy

`App\Services\Content\ContentSlugService` normalizes titles into URL-safe slugs using the configured separator from `config/content.php`. It checks uniqueness while allowing locale-aware slug reuse, so future multilingual content can use the same slug in different locales when appropriate.

## Publishing Lifecycle

`App\Services\Content\ContentService` owns content creation and status transitions:

- New content starts as draft.
- Draft content can be published.
- Draft or published content can be archived.
- Archived content cannot transition back in STEP08.

These conservative rules give future controllers and admin screens a stable service boundary.

## SEO, Localization, And Media Compatibility

SEO fields are stored directly on content records but are not rendered publicly yet. Locale is nullable for global content and future localized content variants. Featured media references the media metadata foundation from STEP07, allowing future content modules to attach images without creating a media picker now.

`App\DTOs\Content\ContentData` exposes only safe presentation fields: title, slug, status, type, and published timestamp. Internal metadata such as author IDs, media IDs, and SEO fields are intentionally excluded.

## STEP09 Email Message Storage Foundation

STEP09 adds storage-only infrastructure for future inbound email processing. It does not receive real email, expose webhooks, poll IMAP, run an SMTP server, parse MIME, store raw payloads, save attachment files, create mailboxes, expose public inboxes, or add admin message screens.

The message model is split into three tables:

- `email_messages`: normalized message metadata, safe body fields, processing state, abuse/quarantine state, retention tier, expiration timestamps, and lifecycle timestamps.
- `email_message_recipients`: normalized recipient rows for `to`, `cc`, and `bcc`, including email, optional name, local part, and domain.
- `email_attachments`: attachment metadata only, with optional future `media_id`, filenames, MIME type, size, checksum, scan status, storage labels, and status.

No raw MIME payloads or attachment binaries are stored in these tables.

## Email Message Lifecycle

Message status is represented by `EmailMessageStatus`: received, queued, processed, failed, quarantined, expired, and deleted. Parse state is represented separately by `EmailParseStatus`: pending, parsing, parsed, and failed.

`App\Models\EmailMessage` provides helpers for expiration, quarantine, processed state, and basic processed/failed transitions. These helpers are intentionally small because MIME parsing and queues are deferred.

## Attachment Metadata Strategy

`EmailAttachment` stores metadata only. `media_id` is nullable so STEP09 can track attachments before future file storage exists. A future inbound pipeline may create media records after validation/scanning and link them back to attachment metadata.

Attachment scan status is represented by `EmailAttachmentScanStatus`: pending, clean, suspicious, infected, and skipped. STEP09 does not perform scanning.

## Retention Strategy

`RetentionTier` supports short, standard, and premium retention. `EmailRetentionService` calculates expiration timestamps from `config/retention.php` and exposes an expired-message query for cleanup.

`mail:cleanup-expired` is a conservative command foundation. By default it marks expired messages as expired. It only soft-deletes when `EMAIL_EXPIRED_MESSAGE_ACTION=delete` is explicitly configured. It does not delete attachment files.

## Future Compatibility

STEP10 builds on this storage layer with queue-first inbound intake workers that call `EmailMessageStorageService` with normalized arrays. STEP12 can build public inbox behavior on top of stored messages, recipients, retention state, and attachment metadata without changing this schema. API endpoints, realtime events, mailbox generation, and admin message screens remain future modules.

## STEP10 Inbound Mail Processing Queue

STEP10 adds a queue-first inbound intake foundation. It does not add production webhook endpoints, Mailgun/Postmark/SES integrations, SMTP handling, IMAP polling, raw MIME parsing, attachment file storage, public inbox UI, admin intake review UI, or API endpoints.

Inbound attempts are stored in `inbound_mail_intakes` with provider metadata, signature status, private headers and payload JSON, normalized payload JSON, failure messages, and processing timestamps. Payloads are private operational records and are never exposed publicly.

## Queue-First Strategy

Controllers and future webhook handlers must not parse or store messages synchronously. Their future responsibility is limited to receiving a request, creating an intake through `InboundMailIntakeService`, verifying the provider signature, and dispatching `ProcessInboundMailIntake`.

`ProcessInboundMailIntake` loads the intake, marks it processing, asks the configured provider to normalize the payload, stores the message through `EmailMessageStorageService`, and marks the intake processed. The sync queue still works for shared-hosting and testing, while database/Redis queues can be used later on VPS deployments.

## Provider Contracts

Inbound providers implement `InboundProviderContract`:

- `provider()`
- `verifySignature()`
- `normalizePayload()`

Signature verification can also be represented by `InboundSignatureVerifierContract`. Real provider secrets and algorithms are intentionally deferred.

The local provider exists for testing and local simulation only. It can allow unsigned payloads in local/testing or require a configured local token. It maps safe arrays into the STEP09 message storage shape without parsing MIME.

## Failure Handling

Failed processing marks the intake failed, records a short safe error message, and avoids stack traces in the database. Original payloads remain private for future admin review.

Invalid signatures are rejected before queue processing. Rejected intakes do not create email messages.

## Duplicate Prevention

`InboundMailIntakeService` performs basic duplicate checks by provider plus provider message ID or provider plus intake key. Existing duplicate intakes are returned instead of creating a second intake or duplicate message.

## Future Compatibility

STEP11 can add real provider adapters behind the existing contracts. STEP12 can build public inbox behavior on top of normalized messages created through the same storage service. Future provider webhook routes should be disabled by default until explicitly configured and must keep queue-first behavior.

## STEP11 Installer Foundation

STEP11 adds a first-time setup and recovery foundation without introducing billing, licensing, updates, marketplaces, dashboards, or deployment automation. The installer lives under the reserved `/install` path and uses route names in the `installer.*` namespace.

The installer flow is:

1. Welcome
2. Requirements
3. Environment
4. Database
5. Finish

The finish action creates an application key when missing, creates the installer lock, and redirects to `/admin/login`. The admin login path remains a future admin surface; STEP11 does not create an admin dashboard or staff login implementation.

## Installation State Detection

`App\Services\System\InstallationService` returns structured status arrays for the current setup state. It checks whether the environment file exists, whether `APP_KEY` is configured, whether the database is reachable, whether the installer lock exists, and whether the installer should be accessible.

The service never exposes secret values. It reports booleans and safe labels only, so public recovery pages can explain what is wrong without rendering keys, passwords, tokens, or full exception details.

## Installer Lock Strategy

`App\Services\System\InstallerLockService` uses a storage-backed lock file at `storage/app/install.lock`. This is intentionally shared-hosting friendly because it requires no daemon, external cache, or database table. The lock survives process restarts and normal deploys as long as the storage directory is preserved.

The lock blocks installer access only when the application is otherwise healthy. If recovery signals appear, the installer can reopen safely.

## Recovery Strategy

Recovery mode activates automatically when:

- `.env` is missing.
- `APP_KEY` is missing.
- The application is not installed.
- Database configuration or connectivity cannot be verified.

This avoids manual code edits during first-time setup or broken configuration recovery. Recovery is conservative: it reopens only the installer foundation and does not run migrations or destructive database actions automatically.

## Environment Writer Design

`App\Services\System\EnvironmentWriterService` updates `.env` files without replacing unrelated keys. Existing keys are updated in place, missing keys are appended, and new files are created when necessary. The writer preserves the file's Windows or Linux line ending style where possible and returns only key names that were written, not secret values.

Future installer screens can use this service for validated environment input while keeping raw environment access out of controllers and views.

## Database Validation Design

`App\Services\System\InstallerDatabaseService` checks whether the configured database driver is available and whether a safe `select 1` query can run on the configured connection. It catches connection failures and returns structured status instead of surfacing raw exceptions to users.

STEP11 does not automatically migrate. Migration and seed execution remain explicit future installer steps so the application can stay safe on shared hosting and recover from partial configuration issues without modifying schema unexpectedly.

## Middleware Boundaries

`EnsureInstallerAccessible` protects the `/install` route group and prevents setup screens from being shown when the application is fully installed and healthy. `EnsureApplicationInstalled` is available for future protected route groups that should redirect to the installer when installation is incomplete.

The installer middleware avoids redirect loops by allowing installer paths through and by keeping the current public, auth, localization, RBAC, media, content, and mail foundations unchanged.

## STEP12 Public Inbox UI Foundation

STEP12 adds a public temporary inbox foundation using Blade, Alpine.js, and Tailwind CSS. It does not add real inbound providers, webhooks, SMTP, IMAP, MIME parsing, attachment downloads, API business endpoints, billing limits, admin mail UI, or persistent mailbox ownership.

Public routes are:

- `GET /inbox`
- `POST /inbox/generate`
- `POST /inbox/rotate`
- `POST /inbox/forget`
- `GET /inbox/messages`
- `GET /inbox/messages/{uuid}`

The JSON routes are public read-side foundations only and return data for the current session mailbox.

## Session-Based Mailbox Strategy

`App\Services\Mail\PublicMailboxService` creates a temporary mailbox address and stores it in the session. The generated local part is random, lowercase, alphanumeric, bounded by configuration, and avoids reserved operational names where practical. Domains are selected only from `config/domains.php`, so users cannot inject arbitrary recipient domains.

No mailbox table exists yet. This keeps STEP12 lightweight and compatible with anonymous public inbox usage while leaving room for future persistent mailbox history, user-owned mailboxes, retention rules, and billing limits.

## Public Inbox Configuration

STEP12 adds configuration placeholders for:

- Default public mailbox domain.
- Allowed public mailbox domains.
- Mailbox local part length.
- Mailbox session key.
- Polling interval.
- Public inbox feature flag.

The domain pool is config-backed only. A future domain module can replace this with database-backed inventory without changing controller behavior because mailbox generation is already behind a service boundary.

## Message Read Boundary

`App\Services\Mail\PublicInboxMessageService` reads from the STEP09 email message tables and returns public-safe DTO arrays. It filters by the current session mailbox and hides quarantined, expired, deleted, and already-expired messages. It never returns internal database IDs, foreign keys, storage disks, storage paths, checksums, raw provider payloads, or raw MIME data.

List responses use `PublicInboxMessageData`. Detail responses use `PublicInboxMessageDetailData`. Attachments are represented only as safe metadata placeholders; downloads and previews remain out of scope.

## Safe HTML Rendering Boundary

The public inbox never renders raw `html_body`. Detail JSON prefers `sanitized_html_body` when it exists and falls back to escaped text body in the UI. The Blade and Alpine foundation do not use raw Blade HTML output for email content, do not iframe messages, do not auto-load remote images, and do not execute scripts.

STEP12 does not implement a sanitizer engine. It preserves the boundary so a future MIME/sanitization step can populate `sanitized_html_body` safely before public rendering.

## Polling And Realtime Compatibility

The inbox page includes an Alpine.js polling foundation using the configured polling interval and a manual refresh button. Polling is rate-limited through Laravel's `RateLimiter`, which works on shared hosting with the existing cache driver.

Future realtime delivery through WebSockets, Reverb, server-sent events, or provider webhooks can reuse the same read service and DTOs. Polling remains the fallback path for shared-hosting deployments.

## Future Cleanup Compatibility

Because the session stores only the current mailbox address, cleanup can continue to operate at the message-retention layer from STEP09 and STEP10. Future mailbox cleanup, user-owned inbox history, domain rotation, billing limits, and abuse controls can be added without changing the anonymous public route contract introduced in STEP12.

## STEP13 Cleanup And Retention System

STEP13 centralizes mail and inbound intake retention without adding an admin dashboard, attachment file deletion, storage lifecycle automation, billing retention rules, or queue monitoring dependencies.

`config/retention.php` owns conservative defaults for tier-based email expiration, chunk size, dry-run behavior, hard-delete permission, inbound intake retention, privacy-safe audit logging, attachment metadata behavior, and optional scheduler frequency.

## Retention Lifecycle

`App\Services\Mail\EmailRetentionService` remains the source for tier-based message expiration. Existing `expirationFor()` behavior is preserved, while `determineExpirationDate()` and `isExpired()` provide explicit lifecycle helpers for future callers.

`App\Services\Mail\MailCleanupService` processes expired records with `chunkById`. This avoids loading large result sets into memory and keeps cleanup suitable for shared-hosting cron execution.

The default message lifecycle is conservative:

1. Locate messages whose `expires_at` timestamp has passed.
2. Mark them as expired.
3. Leave physical attachment files untouched.
4. Hide expired records from the STEP12 public inbox.

Hard deletion occurs only when `RETENTION_HARD_DELETE_ENABLED=true`. When enabled, the service deletes recipient rows and attachment metadata before permanently deleting the message. Storage files are intentionally left untouched for a future storage cleanup step.

## Inbound Intake Cleanup

Old processed, failed, and rejected inbound intake records are deleted after the configured intake retention window. Cleanup uses chunks and never writes headers, normalized payloads, raw payloads, message bodies, or other sensitive intake details to console output or audit records.

## Cleanup Audit Strategy

Each full cleanup creates a privacy-safe `cleanup_runs` audit record when cleanup logging is enabled. Audit rows store a UUID, cleanup type, status, dry-run state, aggregate counters, timestamps, and a short safe error classification only.

Audit rows never store mailbox addresses, subjects, bodies, attachment paths, provider payloads, credentials, or stack traces. `CleanupRun` exposes `isRunning()`, `isCompleted()`, and `isFailed()` helpers for future operational screens.

## Dry-Run And Command Strategy

`mail:cleanup-expired` now delegates to `MailCleanupService`. It supports:

- `--dry-run`
- `--chunk=`

The command outputs aggregate counters only. Dry-run mode scans records and reports the expected impact without mutating messages, intakes, recipients, or attachment metadata.

## Scheduler Compatibility

Laravel 13 scheduler registration lives in `routes/console.php`. When `RETENTION_SCHEDULE_ENABLED=true`, the cleanup command runs hourly by default or daily when configured. `withoutOverlapping()` prevents concurrent executions where the configured cache driver supports scheduler locks.

Shared-hosting deployments can invoke Laravel's scheduler from a standard cron entry and do not require Horizon, Pulse, Redis, or a long-running worker.

## Future Compatibility

The cleanup service leaves attachment file deletion, object-storage lifecycle rules, mailbox history cleanup, admin cleanup dashboards, and billing-tier controls for later steps. The aggregate audit model and service boundaries are ready for those features without exposing private mail data.

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
