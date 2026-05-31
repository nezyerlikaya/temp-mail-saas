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

## STEP23 Admin Operations Center

STEP23 introduces the first real operational staff interface. It is read-only and builds on the staff/RBAC, health, queue, domain, abuse, billing, cleanup, operations, and audit foundations created in earlier steps.

The admin route space remains stable:

- `/admin`
- `/admin/operations`
- `/admin/health`
- `/admin/queue`
- `/admin/domains`
- `/admin/abuse`
- `/admin/billing`
- `/admin/audit`

All routes use staff guard state through the existing staff middleware and enforce RBAC permissions server-side. Unauthenticated or unauthorized access returns `403`.

## Operations Center Permissions

STEP23 adds these permission slugs to the existing permission map:

- `operations.view`
- `health.view`
- `queue.view`
- `billing.view`
- `audit.view`

Existing `domains.view` and `abuse.view` permissions are reused. The permission model and role strategy are not replaced.

## Dashboard Widgets

The operations dashboard summarizes:

- System health counts.
- Readiness indicators.
- Queue pending, processed, and failed totals.
- Latest cleanup run.
- Abuse event counts.
- Billing customer/subscription/invoice counts.
- Domain health totals.

Widgets are intentionally informational. STEP23 does not add retry buttons, destructive actions, DNS actions, refunds, moderation actions, backup restore, or settings changes.

## Centers

The health, queue, domain, abuse, billing, and audit centers are read-only Blade screens using the shared admin layout and reusable card/table/empty-state components.

Audit visibility aggregates:

- `CleanupRun`
- `OperationsEvent`
- `BillingWebhookEvent`

Sensitive metadata such as raw hashes, payment data, card data, and destructive operational controls are excluded from the UI.

## Future Action Center Compatibility

Future steps can add explicit action centers or workflows behind new permissions. STEP23 keeps this first admin surface read-only so operational visibility and authorization boundaries stabilize before mutation features are introduced.

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

## STEP14 Abuse Detection And Rate Limit System

STEP14 adds a privacy-first abuse detection and rate-limit foundation without introducing an admin dashboard, moderation UI, external CAPTCHA provider, IP ban management, WAF integration, billing-tier controls, or raw device fingerprinting.

The foundation protects public inbox generation, mailbox rotation, inbox polling, message detail reads, login attempts, and registration attempts. It uses Laravel's cache-compatible rate limiter, so shared-hosting deployments do not require Redis.

## Multi-Signal Abuse Strategy

`App\Services\Abuse\AbuseSignalService` derives safe request signals from the IP address, session identifier, optional authenticated user ID, user agent, route name, endpoint, and HTTP method. Raw IP addresses, raw session identifiers, and raw user-agent values are never returned or persisted.

The limiter uses a hashed IP signal as its stable base, adds an authenticated user signal when available, and falls back to the hashed session signal when an IP signal is unavailable. This keeps limiter keys privacy-safe while avoiding easy bypass through session rotation.

## Privacy-First Hashing And Logging

Signal hashing uses HMAC SHA-256 with the configured abuse salt. `App\Services\Abuse\AbuseLoggerService` stores aggregate and operational metadata only. It removes sensitive metadata keys such as payloads, bodies, headers, credentials, email values, raw IP values, session values, and user-agent values before writing.

`abuse_events` stores UUID, event type, severity, status, hashed signals, optional user references, safe route metadata, risk score, safe reason, sanitized metadata, and timestamps. It never stores raw request payloads, email content, mailbox content, credentials, raw network identifiers, or raw browser identifiers.

Logging silently fails when the database is unavailable during installer or recovery scenarios. Abuse logging must not block installation recovery or public-safe error handling.

## Rate Limit Profiles

`App\Services\Abuse\RateLimitProfileService` centralizes conservative per-minute profiles and cooldown placeholders for:

- Mailbox generation
- Mailbox rotation
- Inbox polling
- Message detail reads
- Login attempts
- Registration attempts

Profiles live in `config/abuse.php` and are ready for future account-tier extension without embedding policy in routes or controllers.

## Progressive Decisions

`App\Services\Abuse\AbuseDecisionService` returns a structured decision with:

- `allowed`
- `status`
- `risk_score`
- `cooldown_seconds`
- `requires_captcha`
- `reason`

The initial risk score is intentionally simple and cache-independent. It supports observed, throttled, blocked, and escalated outcomes. Progressive cooldowns can increase with risk score. CAPTCHA escalation is configuration-ready only; STEP14 does not call an external CAPTCHA or Turnstile provider.

## Public Inbox Protection

STEP12 public inbox endpoints now use separate hashed-signal limiter profiles. JSON throttles return a generic `429` message without exposing limiter keys, risk details, hashes, or internal policy values. Throttle events are recorded as privacy-safe abuse events when logging is available.

The Alpine inbox UI already handles failed refresh requests gracefully, so polling cooldowns remain compatible with the existing public inbox experience.

## Authentication Observation

Failed login attempts create a low-severity observed event. Login cooldowns create a throttled event. Registration honeypot triggers create a blocked event. These observations preserve existing enumeration-safe auth messages and never store submitted credentials, email addresses, or honeypot values.

## Future Compatibility

STEP14 deliberately has no abuse admin UI. The event schema, safe logger, profiles, and decision service provide a stable foundation for future moderation dashboards, CAPTCHA integration, trusted proxy configuration, account-tier policy, and operational review without changing public endpoint contracts.

## STEP15 Premium Plans And Feature Gates

STEP15 adds Free, Member, and Premium plan differentiation without integrating payment providers, recurring subscriptions, checkout, invoices, coupons, billing webhooks, billing admin screens, or API monetization.

The existing `account_tier` user field remains intact as a backward-compatible fallback. New manual assignments take precedence when an active assignment exists.

## Plan Model

`plans` stores billing-ready plan identity without provider-specific data:

- UUID
- Name
- Unique slug
- Optional description
- Active state
- System-plan state
- Sort order

`PlanSeeder` idempotently creates or updates the system plans:

- `free`
- `member`
- `premium`

The seeder is safe to rerun and leaves room for future billing adapters without coupling plans to Stripe, Paddle, Lemon Squeezy, or another provider.

## Manual Assignment Foundation

`user_plan_assignments` links a user to a plan with optional staff attribution, start time, expiration time, and internal notes. It deliberately stores no payment provider IDs.

Assignments support future staff-managed plan changes while the admin UI remains out of scope. `User::activePlan()` resolves an active assignment whose time window includes the current time. When no active assignment exists, feature resolution falls back to the existing `account_tier` value and then to Free.

Relationships include:

- `Plan::assignments()`
- `Plan::users()`
- `User::planAssignments()`
- `User::activePlan()`
- `StaffUser::assignedPlanAssignments()`

Plan and user helpers provide `isFree()`, `isMember()`, and `isPremium()` checks.

## Feature Gate Strategy

`config/features-gates.php` defines plan-driven values for:

- Mailbox generation limit
- Retention tier
- Inbox polling interval
- Allowed public mailbox domains
- Priority-processing placeholder

`App\Services\Billing\FeatureGateService` resolves the current plan, checks boolean capabilities, returns feature values, and supplies Free-plan fallbacks. It catches plan-table lookup failures so installer and recovery states remain safe before migrations are available.

## Public Inbox Integration

Anonymous inbox visitors use Free-plan defaults. Authenticated users can receive plan-aware mailbox domain selection and polling intervals. Existing config-backed domain inventory remains authoritative: plan gates can narrow an available domain pool but cannot inject a domain outside `config/domains.php`.

Mailbox generation protection remains compatible with STEP14. The effective generation limit is the lower of the global abuse ceiling and the plan quota, so a paid tier cannot bypass abuse safeguards.

## Retention Mapping

`EmailRetentionService::tierForUser()` maps plan configuration to retention tiers:

- Free: short
- Member: standard
- Premium: premium

The existing inbound storage default remains unchanged when no user context exists. This preserves STEP09 and STEP10 behavior while preparing future user-owned mailbox flows for plan-aware retention.

## Future Billing And Checkout Compatibility

STEP15 introduces plan identity and assignment state only. A future billing module can attach provider subscriptions, checkout sessions, webhook state, invoices, and cancellation rules behind new billing services without replacing the current plan schema or public inbox contract.

## STEP16 SEO And Content Foundation Expansion

STEP16 expands SEO and content infrastructure without adding an SEO admin UI, blog frontend, content editor SEO panel, analytics integration, search-engine submission, hreflang management UI, OG image generation, API endpoints, or page builder behavior.

## SEO Architecture

`seo_settings` stores editable SEO key/value records with an optional group and public/private distinction. The table is future admin compatible while remaining safe for current public rendering. `SeoSettingSeeder` idempotently seeds:

- `site_name`
- `default_title`
- `default_description`
- `default_robots`

`App\Services\Seo\SeoService` is the central metadata resolver. Controllers and views should not assemble SEO arrays directly. The service merges public settings, config defaults, page-specific overrides, and optional content metadata into `SeoMetaData`.

## Metadata Strategy

`SeoMetaData` exposes only presentation-safe fields:

- Title
- Description
- Canonical URL
- Robots
- Open Graph title, description, and image
- Twitter/X Card title, description, and image

The application layout renders metadata from the service in one place to avoid duplicate meta tags. Existing pages can continue passing a `$title` value, which becomes a page-specific override.

## Canonical Strategy

Canonical URLs are generated from the current request without query strings unless explicitly overridden. This keeps UTM parameters and other transient query values out of canonical tags while allowing future content routes to provide stable canonical URLs.

## Sitemap Strategy

`App\Services\Seo\SitemapService` returns cache-friendly sitemap entries for configured static pages and published content. It intentionally uses foundation URLs for content entries and does not introduce a blog frontend. Future localized routes, blog routes, and content-type-specific URLs can extend the same service.

Temporary mailbox URLs and inbox session state are excluded from the sitemap foundation.

## Robots Strategy

`App\Services\Seo\RobotsService` generates a simple robots response with environment-aware behavior. Production allows crawling, while non-production disallows crawling by default. The service includes the sitemap reference when sitemap support is enabled.

## Structured Data Strategy

`App\Services\Seo\StructuredDataService` returns schema.org-compatible arrays for website, organization, and article foundations. It does not render script tags directly; frontend rendering can be added later through layout components or content templates.

## Content SEO Integration

The content table already stores `meta_title` and `meta_description`. STEP16 adds model helpers:

- `Content::seoTitle()` falls back from `meta_title` to `title`.
- `Content::seoDescription()` falls back from `meta_description` to `excerpt`.

This keeps content SEO behavior centralized and ready for future admin SEO editing without changing the existing content service or DTO safety boundary.

## Future Admin SEO Compatibility

The settings table, service layer, sitemap service, robots service, structured data service, and content helpers provide the foundation for future admin SEO screens. Admin editing, validation workflows, preview tools, generated OG images, hreflang management, and search-engine submission remain future steps.

## STEP17 API Access Foundation

STEP17 prepares API access without exposing mailbox, message, domain, billing, or other business API endpoints. It does not add an API dashboard, API key UI, OpenAPI documentation, SDK generation, or webhooks.

## API Key Architecture

`api_keys` stores user-owned API credentials using a UUID, display name, key prefix, hashed key, status, optional expiration and revocation timestamps, safe metadata, and last-used time. Raw API keys are never stored.

`App\Services\Api\ApiKeyService` generates secure random keys, stores only an HMAC hash, verifies incoming keys by prefix plus hash, revokes keys, rotates keys, and returns the raw key only at creation or rotation time. Rotation revokes the old key and creates a new credential record.

## Hashing Strategy

API key hashing uses HMAC SHA-256 with the application key as the secret. The database keeps only:

- A short prefix for lookup.
- A full hash for verification.

This keeps key verification efficient without storing recoverable secrets.

## Authentication Strategy

`App\Services\Api\ApiAuthService` resolves bearer tokens from `Authorization: Bearer ...` and also supports `X-API-Key` for future compatibility. It returns a structured auth result with the API key, resolved user, and failure reason.

`AuthenticateApiKey` middleware validates the credential, attaches `api_key` and `api_user` request attributes, and sets the request user resolver. Invalid credentials receive a generic JSON `401`; disabled plan access receives a generic JSON `403`.

## Usage Logging

`api_usage_logs` records safe aggregate request information only:

- API key reference
- Endpoint
- Method
- Response status
- Request count
- Occurrence time

It does not store request payloads, response bodies, mail data, headers, credentials, or raw keys. `ApiUsageLoggerService` can silently skip logging when disabled or unavailable.

## Rate Limit And Feature Gate Strategy

`App\Services\Api\ApiRateLimitService` resolves plan-aware API access state and per-minute limits through `FeatureGateService`. STEP15 feature gates now include:

- `api_enabled`
- `api_rate_limit_per_minute`

Free accounts default to API disabled, Member receives moderate limits, and Premium receives larger limits. STEP17 does not enforce endpoint-specific throttles yet because no business endpoints exist; the service is ready for future endpoint policies and abuse-system integration.

## Route Foundation

`routes/api.php` is now registered under Laravel's `/api` prefix. The only placeholder route is:

- `GET /api/v1/ping`

It is protected by API key middleware and returns a foundation health payload only. It exposes no mailbox, message, domain, billing, or user business data.

## Future API Compatibility

Future public API work can add scoped permissions, endpoint-specific limits, API dashboards, OpenAPI docs, webhooks, and mailbox/message/domain APIs on top of this foundation without replacing key storage, hashing, auth, usage logging, or feature gate integration.

## STEP18 Production Hardening

STEP18 prepares the application for production deployment without adding Horizon, Pulse, external error tracking providers, backup execution, restore execution, Kubernetes, Docker orchestration, CI/CD pipelines, or monitoring dashboards.

The hardening foundation is shared-hosting compatible first and VPS/cloud compatible second. Services return structured, privacy-safe reports and avoid stack traces, secrets, payloads, credentials, raw API keys, raw IP values, and email content.

## Health Monitoring Strategy

`system_health_checks` stores health-focused audit rows with:

- UUID
- Check name
- Health status
- Safe message
- Safe metadata
- Checked timestamp

`SystemHealthStatus` supports `healthy`, `warning`, and `critical`. `SystemHealthCheck` exposes `isHealthy()`, `isWarning()`, and `isCritical()` helpers.

`App\Services\System\SystemHealthService` checks:

- Database connectivity
- Cache availability
- Storage permissions
- Queue configuration
- Scheduler readiness
- Installer lock state
- Application key presence

The existing `/health` route remains backward compatible. STEP18 adds deeper internal checks and the `system:health-check` command for operational use.

## Production Readiness Strategy

`App\Services\System\ProductionReadinessService` evaluates production-safe configuration and returns aggregate counts for passed checks, warnings, and failures. It checks debug mode, app key presence, HTTPS expectation, queue driver compatibility, mail transport placeholders, and writable storage.

`system:readiness-check` displays only safe check names and aggregate counts. It does not expose environment values or secrets.

## Error Tracking Abstraction

`App\Services\System\ErrorTrackingService` centralizes error reporting behind a local logging fallback. It is ready for future Sentry or Bugsnag adapters without adding those providers now.

Context data is sanitized before logging. Passwords, tokens, secrets, API keys, payloads, request bodies, and email bodies are removed from error-report context.

## Backup Readiness Philosophy

`App\Services\System\BackupReadinessService` verifies that configured backup source paths are readable and that the destination disk is configured. It does not create archives, run backup jobs, delete files, or perform restores.

This keeps backup readiness safe for shared hosting while preparing future backup execution and restore workflows.

## Scheduler Preparation

`routes/console.php` can schedule `system:health-check` when `SYSTEM_HEALTH_SCHEDULE_ENABLED=true`. The default frequency is hourly, with daily support through configuration. `withoutOverlapping()` is used where Laravel's scheduler lock support is available.

Shared-hosting deployments can run Laravel's scheduler through a normal cron entry and do not require Redis, Horizon, or a long-running process.

## Logging Hardening

STEP18 reinforces the privacy rules established in prior steps:

- No API keys in logs.
- No raw IP values where hashing is already used.
- No passwords or secrets.
- No email bodies or request payloads.
- No stack traces in public health/readiness output.

Operational records store only safe class names, check names, statuses, counts, and sanitized context.

## Deployment Preparation

The production foundation gives deployers a local, provider-free way to answer three questions before launch:

1. Is the application healthy right now?
2. Is the production configuration safe enough to deploy?
3. Are backup paths and destination settings ready for future backup execution?

Future observability and operations center work can build dashboards on top of the health table, readiness service, error tracking abstraction, and backup readiness checks without replacing these foundations.

## STEP19 Observability And Operations Center Foundation

STEP19 adds operations and observability infrastructure without introducing an admin dashboard, Horizon, Pulse, Grafana, Prometheus, external monitoring, alert emails, Slack, or Discord integrations.

The foundation remains shared-hosting compatible and privacy-first. Operational records never store secrets, request payloads, email bodies, raw job payloads, raw exceptions, passwords, tokens, or API keys.

## Operations Event Model

`operations_events` stores dashboard-ready operational events with:

- UUID
- Category
- Event type
- Severity
- Status
- Optional source
- Safe message
- Sanitized metadata
- Occurrence timestamp

Enums define stable values:

- `OperationCategory`: system, queue, domain, abuse, api, mail, scheduler
- `OperationSeverity`: info, warning, error, critical
- `OperationStatus`: detected, acknowledged, resolved

`OperationsLoggerService` centralizes event creation and metadata sanitization. It removes sensitive keys such as payloads, bodies, secrets, tokens, API keys, passwords, raw content, and exception details.

## Queue Monitoring Philosophy

`queue_metrics` records aggregate queue counts only: queue name, pending jobs, failed jobs, processed jobs placeholder, and measurement time.

`QueueMonitorService` works from Laravel's standard `jobs` and `failed_jobs` tables and requires no Horizon or Redis. It can create warning events when configured pending or failed-job thresholds are exceeded.

Processed job counts remain a placeholder because Laravel's database queue does not retain completed job rows by default.

## Domain Health Monitoring Philosophy

`domain_health_checks` stores format and readiness-oriented checks for configured public mailbox domains. `DomainHealthService` evaluates the configured domain pool, assigns a score, and records healthy, warning, or critical status.

STEP19 does not perform DNS checks, blacklist lookups, provider calls, or domain verification. Future domain pool management can extend the service with those checks without changing the table purpose.

## Failed Job Observability

`FailedJobMonitorService` summarizes failed job counts and groups them by queue. It creates an operational event when failures exist, but it never stores failed job payloads or exception text in operations metadata.

This gives future dashboards enough signal to show failed-job pressure while preserving privacy and avoiding accidental leakage of queued payloads.

## Metrics Collection Strategy

`SystemMetricsService` gathers structured arrays for:

- App environment and debug state
- Storage writability
- Health record counts
- Cleanup run counts
- Abuse event counts
- API usage log counts
- Queue metrics
- Domain health checks
- Failed job summaries

`operations:collect-metrics` stores queue and domain metrics and generates threshold events. `operations:health-summary` displays safe counts for operational data.

## Scheduler Compatibility

Scheduled metrics collection is config-ready through `OPERATIONS_METRICS_SCHEDULE_ENABLED`. When enabled, Laravel's scheduler can run `operations:collect-metrics` hourly or daily with `withoutOverlapping()`.

Shared-hosting deployments can use a normal cron entry for Laravel's scheduler. No daemon, Horizon, Pulse, Prometheus, or external monitoring service is required.

## Future Operations Dashboard Compatibility

The operations tables and services are intentionally dashboard-ready but UI-free. A future operations center can read operations events, queue metrics, domain checks, health checks, cleanup runs, abuse events, API usage logs, and failed-job summaries without changing existing module boundaries.

## STEP20 Enterprise And Multi-Tenant Foundation

STEP20 adds an enterprise and tenant-aware foundation while keeping the application a shared-database Laravel SaaS. It does not introduce full multi-database tenancy, a tenancy package, organization UI, team invitation emails, SSO/SAML/OIDC, custom domain verification, SSL automation, enterprise admin dashboards, domain ownership management, or audit dashboards.

The foundation prepares organization ownership, team membership, tenant context, organization-aware feature gates, and future enterprise boundaries without changing existing public inbox, API, auth, RBAC, installer, abuse, observability, or billing foundations.

## Shared-Database Enterprise Strategy

Organizations are modeled as first-party records in the existing database. Future tenant-aware tables can add nullable `organization_id` columns where ownership is needed, but STEP20 avoids broad schema rewrites. This keeps shared-hosting compatibility and avoids early commitment to separate database tenancy.

No tenancy package is added yet because the current product does not need request-wide database switching, tenant-specific migrations, isolated connections, or tenant route prefixes. The current goal is ownership and context readiness, not hard isolation.

## Organization Model

`organizations` stores:

- UUID
- Name and unique slug
- Status
- Optional owner user
- Optional plan
- Sanitized metadata

`OrganizationStatus` supports active, inactive, and suspended states. `OrganizationService` creates organizations, normalizes unique slugs, assigns owners, sanitizes metadata, and checks organization status.

Organization metadata is intended for safe operational labels only. Secrets, tokens, and passwords are stripped by the service layer.

## Membership Model

`organization_members` connects users to organizations with a role, status, optional inviter, and joined timestamp. The unique `organization_id + user_id` constraint prevents duplicate membership rows.

Roles:

- Owner
- Admin
- Member
- Viewer

Statuses:

- Invited
- Active
- Suspended
- Removed

`OrganizationService` can add members, mark members removed, and check active membership. Removed members remain as non-active membership history rather than being physically deleted.

## Tenant Context Strategy

`TenantContextService` stores the current organization ID in the session using a configurable key. It validates that the current user is an active member of an active organization before accepting or returning context.

Invalid, suspended, inactive, or unauthorized organization context is cleared automatically. STEP20 does not add route prefixes or global tenant middleware; future UI and API work can opt into the context service where needed.

## Feature Gate Resolution Order

`FeatureGateService` now supports organization-aware plan resolution while preserving existing user-plan behavior.

Resolution order:

1. Organization plan when an explicit organization is passed or a valid tenant context exists.
2. Active user plan assignment.
3. User `account_tier` fallback.
4. Free default.

This prepares enterprise plan behavior without adding checkout, recurring billing, invoices, or payment provider identifiers.

## Future SSO Compatibility

`config/enterprise.php` includes SSO placeholders but STEP20 does not implement SAML, OIDC, social login, SCIM, or external identity providers. Future SSO work can attach provider settings to organizations without replacing the membership model.

## Future Custom Domain Compatibility

Enterprise custom domain placeholders live in configuration only. STEP20 does not create domain ownership tables, DNS validation, or SSL automation. Future domain pool management can link domains to organizations once domain ownership workflows are introduced.

## Audit Boundary Preparation

STEP20 establishes organization IDs as a future audit boundary. Existing operations, abuse, and API logs are not broadly rewritten in this step, but future loggers can safely accept organization context from `TenantContextService` and store additive `organization_id` references where appropriate.

## STEP21 Advanced Domain Pool Management

STEP21 adds a domain inventory and assignment foundation without implementing DNS validation, MX validation, SPF validation, DKIM validation, blacklist lookups, SSL automation, custom domains, provider integrations, or an admin domain UI.

The public inbox remains backward compatible: when no active domain inventory is available, mailbox generation falls back to the existing config-backed public mailbox domain list.

## Domain Pool Architecture

`domains` stores the operational domain inventory:

- UUID
- Unique domain
- Status
- Type
- Tier
- Priority
- Health score
- Assignment strategy
- Safe metadata
- Last checked timestamp

Stable enums define:

- `DomainStatus`: active, inactive, maintenance, suspended
- `DomainType`: public, premium, enterprise
- `DomainTier`: free, member, premium, enterprise
- `DomainAssignmentStrategy`: random, weighted, priority, health based

`DomainSeeder` creates demo `.test` domains for development only and is safe to rerun.

## Assignment History

`domain_assignments` records audit-focused assignment history with optional mailbox address, user, organization, safe metadata, and assignment timestamp. It does not store email content, message bodies, provider payloads, secrets, DNS material, or credentials.

`DomainPoolService::recordAssignment()` sanitizes metadata before writing assignment records.

## Assignment Strategies

`DomainPoolService` resolves eligible active domains and applies a configurable strategy:

- Random: choose any eligible domain.
- Priority: prefer the lowest priority value.
- Weighted: prefer stronger combined priority and health.
- Health based: prefer high-health domains while respecting priority.

The default strategy is `health_based`. Selection is local and database-backed; no external provider call is made.

## Health Scoring

`DomainHealthService` now includes domain inventory helpers:

- `calculateHealthScore()`
- `markHealthy()`
- `markWarning()`
- `markCritical()`

These helpers update local health score and create `domain_health_checks` records. STEP21 does not perform live DNS, MX, SPF, DKIM, reputation, or blacklist checks.

## Plan-Aware Domain Selection

Domain eligibility is plan-aware through `FeatureGateService` and `config/domains-pool.php`.

Default tier mapping:

- Free: free domains
- Member: free and member domains
- Premium: free, member, and premium domains
- Enterprise: free, member, premium, and enterprise domains

Feature gates may also provide explicit `domain_tiers` per plan. Global abuse and public inbox behavior remain unchanged.

## Organization Compatibility

`DomainPoolService` accepts an optional organization context. Organization plans can influence domain tier eligibility through the existing organization-aware feature gate resolution from STEP20.

This prepares future enterprise custom domains and organization-owned routing without creating domain ownership tables, DNS validation, SSL automation, or tenant-specific mail routing yet.

## Mailbox Generation Integration

`PublicMailboxService` now asks `DomainPoolService` for domain selection. Generated mailbox addresses record assignment history when the selected domain exists in the inventory.

If the domain inventory is empty or unavailable during installer/recovery scenarios, mailbox generation falls back to config domains to preserve shared-hosting compatibility and existing behavior.

## Future DNS And Provider Compatibility

Future domain pool management can add DNS validation, MX checks, SPF/DKIM checks, blacklist/reputation adapters, provider-specific health signals, custom domain ownership, SSL automation, and admin UI on top of the inventory and assignment foundations without changing public inbox contracts.

## STEP22 Billing And Subscription Production Layer

STEP22 adds provider-agnostic billing and subscription foundations without adding checkout UI, customer portals, subscription management UI, Stripe/Paddle/Lemon Squeezy SDKs, invoice download proxies, coupons, taxes, refunds, payment webhooks to external SDKs, or admin billing dashboards.

The application never stores card numbers, payment method identifiers, raw payment details, or provider secrets.

## Provider-Agnostic Billing Strategy

`BillingProviderContract` defines a narrow provider boundary:

- Provider name
- Webhook verification
- Webhook payload normalization
- Customer resolution
- Subscription resolution
- Invoice resolution

`LocalBillingProvider` implements this contract for local and testing workflows only. It verifies HMAC signatures with a local testing secret and performs no external API calls.

Future Stripe, Paddle, or Lemon Squeezy adapters can implement the same contract without changing webhook routing or billing persistence.

## Customer, Subscription, And Invoice Models

`billing_customers` maps provider customers to either a user or an organization. It stores provider name, provider customer ID, optional email, and sanitized metadata.

`billing_subscriptions` stores provider subscription identity, internal plan mapping, lifecycle status, interval, trial dates, period dates, cancellation dates, and sanitized metadata.

`billing_invoices` stores invoice metadata such as provider invoice ID, status, currency, amount due, amount paid, hosted URL, PDF URL, issue time, paid time, and sanitized metadata.

None of these tables include card data or raw payment method details.

## Webhook Verification Boundary

`POST /billing/webhooks/{provider}` is the only billing webhook route. It is CSRF-excluded specifically for provider callbacks and rate-limited separately. It exposes no checkout or billing UI.

`BillingWebhookService` verifies the provider signature before processing, creates a webhook event record, normalizes the payload through the provider adapter, and then delegates customer, subscription, and invoice persistence to `BillingService`.

Invalid signatures are rejected with a generic response. Raw webhook payloads are not stored by default; only a payload hash is kept.

## Idempotency Strategy

`billing_webhook_events` stores provider, optional event ID, event type, signature status, processing status, payload hash, and safe error classification. Provider plus event ID is unique.

If an already processed event arrives again, it is treated as a duplicate and no second subscription/invoice/customer mutation is performed.

## Card Data Exclusion

Billing metadata sanitization strips card, payment method, secret, and token-like keys before writing customer, subscription, or invoice metadata. Tests assert that billing schemas do not include card fields.

The local provider test payloads are intentionally fake and never include real card data.

## Plan Assignment Integration

`BillingService` maps provider plan IDs to internal `plans` through `config/billing.php`.

When an active subscription resolves to an internal plan:

- User subscriptions create or update a `UserPlanAssignment`.
- Organization subscriptions update the organization's `plan_id`.

Canceled or inactive subscription states update the subscription lifecycle safely but do not create active plan assignments. Manual plan assignments remain supported and are not globally removed by billing updates.

## Future Checkout Compatibility

STEP22 prepares persistence, provider abstraction, webhook idempotency, and plan sync. A future checkout layer can add hosted checkout sessions, customer portals, subscription UI, tax handling, refunds, coupons, invoice presentation, and provider SDKs on top of this foundation without replacing the storage or webhook boundary.

## STEP24 Globalization And Localization Center

STEP24 turns the localization foundation into a permission-protected admin center while preserving the existing runtime locale switch behavior.

## Localization Architecture

Languages remain the source of truth for locale code, display name, native name, active state, default state, sort order, and text direction. Translations remain grouped by language, namespace, and key.

The admin center is implemented under the existing admin layout and uses the shared admin card, table, and empty-state components. Routes are protected through staff RBAC permissions:

- `localization.view`
- `localization.manage`
- `localization.import`
- `localization.export`

All write operations are enforced server-side. UI controls are convenience only and are not treated as security.

## Language Lifecycle

Language management supports create, edit, activate, deactivate, set default, and delete operations with conservative safeguards:

- Exactly one language can be default.
- Setting a new default automatically clears previous defaults.
- Default languages cannot be deleted.
- Default languages cannot be disabled.
- The last active language cannot be disabled.

Only active languages are available to the runtime locale switcher. Invalid or inactive locale requests fall back safely to the configured default and fallback locale chain.

## Translation Management

Translations can be searched, filtered by language, filtered by namespace, paginated, and bulk updated from the admin center. Updates set the translation as custom and write a privacy-safe localization audit entry.

Translation values are stored as text metadata only. STEP24 does not introduce machine translation, marketplace workflows, crowd translation, or frontend editor widgets.

## Import And Export Strategy

JSON export produces grouped translation payloads for a selected language. JSON import accepts grouped payloads in this shape:

```json
{
  "namespace": {
    "key": "value"
  }
}
```

Imports validate the target language, parse JSON without code execution, update duplicate keys safely through `updateOrCreate`, and do not write arbitrary files.

## Progress Tracking

`LocalizationProgressService` compares each language against the default language. It calculates completion percentage, completed count, missing keys, and untranslated values.

For non-default languages, values identical to the default language are treated as untranslated so future translation workflows can prioritize real localization work.

## Audit Logging

`localization_audits` records translation-focused changes only:

- Language context
- Staff user context
- Action
- Translation key when applicable
- Old value
- New value
- Created timestamp

The audit trail avoids sensitive operational metadata and is designed for future admin visibility, compliance summaries, or rollback review without storing unrelated request data.

## RTL Strategy

Runtime layout direction is resolved through `LocaleService::directionFor()` and applied to the shared app layout through the root `dir` attribute. Admin pages continue to reuse the existing layout stack and do not duplicate templates for RTL.

Language records expose direction awareness, allowing future frontend components, content previews, email templates, and marketing pages to adapt to RTL without changing routing or replacing layouts.

## STEP25 Marketplace And Integrations Platform

STEP25 adds the integrations foundation without adding a public marketplace, OAuth providers, external API calls, app-store billing, or connector-specific business workflows.

## Integration Registry Architecture

`integrations` stores registry metadata for available integrations:

- UUID and slug identity
- Display name and optional description
- Category
- Lifecycle status
- Version
- Compatibility and marketplace metadata

`IntegrationRegistryService` is the only foundation service that creates or resolves registry entries. It normalizes slugs, supports metadata lookup, and validates active integrations before future modules connect to them.

Registry records are intentionally provider-neutral. Future Slack, Discord, Google, Stripe, or custom connectors can be added as entries without changing the database shape.

## User And Organization Integrations

`user_integrations` connects an integration to either a user or an organization. This preserves enterprise compatibility from the organization foundation.

Configuration is stored through Laravel encrypted casts and backed by text storage so secrets or provider configuration are not exposed as plaintext. The local connector also strips common sensitive keys such as `secret`, `token`, and `password` before storing configuration.

Statuses are represented by `UserIntegrationStatus`:

- Connected
- Disconnected
- Suspended

## Webhook Architecture

`outbound_webhooks` stores outbound webhook configuration for users or organizations:

- UUID identity
- Target URL
- Status
- Secret hash
- Subscribed event names
- Last delivery timestamp

Webhook secrets are never stored in plaintext. `OutboundWebhookService` hashes incoming secrets and rotates secrets by returning the new one-time value while only persisting its hash.

`webhook_deliveries` stores delivery audit metadata only:

- Webhook reference
- Event name
- Delivery status
- Response code
- Delivered timestamp
- Payload hash

Raw webhook payloads are not stored. This keeps the foundation privacy-first and avoids unnecessary sensitive data retention.

## Event Subscription Strategy

`EventSubscriptionService` manages event subscription lists, resolves active webhooks for a given event, and prepares delivery audit records through `OutboundWebhookService`.

No outbound HTTP delivery is performed in STEP25. A future queued delivery worker can consume pending `webhook_deliveries`, sign payloads using the webhook secret model, perform HTTP calls, and record delivery outcomes without changing subscription storage.

## Connector Architecture

`ConnectorContract` defines the connector boundary:

- Connector name
- Connect
- Disconnect
- Configuration validation

`LocalConnector` is a no-network example implementation. It creates encrypted user integration records, supports disconnects, and provides a safe contract target for tests and future connector scaffolding.

Future external connectors should implement the same contract and keep provider-specific SDKs or OAuth flows behind connector classes rather than leaking provider logic into controllers or models.

## OAuth Readiness Strategy

`config/integrations.php` includes disabled OAuth placeholders for provider settings, redirect handling, state TTL, connector registry entries, webhook options, marketplace categories, and compatibility metadata.

OAuth is not implemented in STEP25. The config shape reserves the extension points future provider integrations will need while keeping shared-hosting deployments functional by default.

## RBAC Integration

STEP25 adds these permissions to the existing RBAC configuration:

- `integrations.view`
- `integrations.manage`
- `webhooks.view`
- `webhooks.manage`

Server-side enforcement continues to use the existing staff permission gate and middleware foundation. Future admin screens or API endpoints should require these permissions rather than relying on UI visibility.

## Marketplace Preparation Strategy

The marketplace remains infrastructure-only. Categories, version metadata, compatibility metadata, connector registry placeholders, and lifecycle statuses are available for future public or admin marketplace modules.

No public marketplace UI, connector billing, OAuth provider, external API integration, or app-store behavior is introduced in STEP25.

## STEP26 Advanced Intelligence And Automation Foundation

STEP26 adds a safe automation and intelligence layer without external AI providers, generated content, machine learning models, vector databases, embeddings, or recommendation UI.

## Automation Architecture

Automation is represented by two persistence models:

- `automation_rules`
- `automation_executions`

Rules define a trigger type, optional structured condition group, action type, priority, status, and metadata. Executions record the audit trail for matched rules, including trigger source, status, result summary, timestamps, and sanitized metadata.

Rules are intentionally data-driven. They do not store executable code, scripts, closures, or arbitrary callbacks.

## Rule Engine Strategy

`AutomationEngine` consumes a trigger type and a privacy-safe payload, finds active matching rules, evaluates conditions, creates execution records, and delegates internal action handling to `AutomationExecutionService`.

`RuleEvaluator` supports deterministic condition groups:

- `all`
- `any`
- Single condition objects

Supported operators are limited to safe comparisons such as equals, not equals, numeric comparisons, contains, in, and exists. Field access is limited to simple dot paths and does not execute expressions.

## Execution Strategy

`AutomationExecutionService` creates execution records, marks them running, performs internal action preparation, then records completed or failed status.

STEP26 action types are foundation placeholders:

- Notify
- Log
- Score
- Tag
- Queue job

No destructive actions, outbound calls, provider calls, or arbitrary job dispatching are performed in this step. Sensitive payload fields such as secrets, tokens, raw payloads, content, and passwords are excluded from execution metadata.

## Intelligence Scoring Strategy

`IntelligenceService` records bounded 0-100 scores in `intelligence_scores`.

Initial scoring support covers:

- Abuse risk
- Domain health
- Queue health

The score table is generic enough for future engagement, retention, deliverability, usage, and recommendation signals. Scores store reference type and reference ID, but do not store raw source payloads.

## Event-Driven Architecture

The automation engine can consume existing foundation records:

- Abuse events
- Operations events
- Domain health checks
- Billing webhook events
- Queue metrics

Each event type maps to an `AutomationTriggerType`, allowing future modules to emit or consume automation triggers without changing the source module schemas.

## Scheduler Foundation

`config/automation.php` includes disabled-by-default scheduler switches for:

- Scheduled automation evaluation
- Intelligence recalculation

`routes/console.php` registers schedule callbacks only when those config switches are enabled. This keeps shared-hosting deployments quiet by default while preserving a VPS-friendly path for scheduled workers.

## AI Readiness Strategy

STEP26 prepares an AI-compatible boundary without implementing AI:

- Scores are structured and referenceable.
- Automation outcomes are auditable.
- Conditions are deterministic and explainable.
- Raw payloads are intentionally excluded.
- External AI is disabled in configuration.

Future AI providers can be added behind dedicated services and permission gates without replacing the automation tables, execution history, or scoring model.

## Future Recommendation Systems

Future recommendation features can read from `intelligence_scores`, automation execution summaries, operations events, abuse events, and billing/domain signals. Recommendations should remain explainable, permission-gated, and privacy-safe, with UI and provider integrations added as separate modules.

## RBAC Integration

STEP26 adds these permissions:

- `automation.view`
- `automation.manage`
- `intelligence.view`

No UI is introduced in STEP26. Future admin or API surfaces should enforce these permissions server-side.

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

## STEP30 Production Release Candidate

STEP30 adds the RC1 readiness layer without changing product architecture or provisioning external infrastructure.

## RC1 Readiness Architecture

`ProductionReadinessChecklistService` aggregates production checks into:

- Blockers
- Warnings
- Recommendations
- Informational passed checks

The checklist reviews deployment configuration, writable paths, backup readiness, monitoring readiness, and go-live route availability.

`ReleaseStatusService` evaluates the checklist and returns one of three release states:

- `ready`
- `warning`
- `blocked`

The service is read-only. It does not execute deployments, backups, restores, queue workers, or infrastructure changes.

## Release Status Command

`system:release-status` displays a safe summary:

- Release state
- Target
- Summary
- Blocker count
- Warning count
- Recommendation count
- Safe finding names and messages

The command exits successfully for `ready` and `warning` states, and fails for `blocked` states so it can be used in manual release checklists or future CI pipelines.

## Production Configuration

`config/production.php` now includes RC1 options for release readiness behavior, deployment writable paths, monitoring readiness checks, queue/mail warning policy, and HTTPS warning policy.

Defaults remain shared-hosting compatible. They warn on sync queues and log mailers, but do not provision infrastructure or require external services.

## Backup And Monitoring Readiness

The RC1 checklist reuses `BackupReadinessService`. It verifies readable backup source paths and configured backup destination disk without creating backup archives or attempting restores.

The checklist also verifies health/status route registration and that `SystemHealthService` returns structured checks. Operations metrics and domain health checks can be required by configuration, but are not required by default so shared-hosting deployments can reach RC1 without external monitoring infrastructure.

## Operational Release Notes

RC1 readiness is a gate, not a deployment engine. Production deployment should still run environment review, migrations, writable path checks, scheduler setup if enabled, queue worker setup if async queues are enabled, manual browser QA, and backup/restore verification.

## STEP33 Performance, Scalability And Load Hardening

STEP33 adds performance foundations without changing routes, product behavior, or deployment requirements.

The database layer now has additive composite indexes for high-growth query paths:

- Public inbox message visibility and recent polling.
- Provider intake lookup, duplicate detection, and provider status aggregation.
- Cleanup, abuse, operations, queue, domain pool, domain assignment, billing, webhook, and domain health reporting paths.

`config/performance.php` centralizes safe defaults for cache TTLs, query thresholds, inbox polling limits, domain pool health thresholds, and dashboard aggregation limits. The defaults remain compatible with shared hosting and can be increased on VPS deployments.

`PerformanceCacheService` provides config-driven caching for health summaries, readiness summaries, localization progress, domain health summaries, and operations dashboard data. Cache failures degrade to uncached reads so the application remains available when a shared-host cache backend is missing or temporarily unavailable.

The operations dashboard was tuned to reduce repeated count queries by grouping status and summary counts. Audit and domain health widgets use configurable limits to prevent accidental large reads.

`LoadReadinessService` now reports database readiness, cache readiness, queue readiness, provider throughput, cleanup throughput, intake throughput, and admin route readiness. The service is observational only; it does not generate load or mutate production data.

Public inbox listing now uses the performance-configured polling limit while preserving the existing response shape and privacy guarantees.

Domain pool selection now respects a configurable minimum health threshold and keeps its existing fallback domain behavior when inventory is empty or inactive.

## STEP34 Production Operations And Monitoring

STEP34 adds a vendor-neutral production monitoring foundation while preserving the existing modular monolith architecture.

New metadata-only operational tables:

- `incidents`
- `monitoring_alerts`

Incidents track operational impact using category, severity, status, title, summary, detection time, optional resolution time, and sanitized metadata. Monitoring alerts track source, alert type, severity, status, message, trigger time, and lifecycle timestamps.

`IncidentService` owns incident creation, acknowledgement, resolution, severity handling, categorization, and metadata sanitization.

`MonitoringService` aggregates existing signals from:

- Queue metrics.
- Provider operations events.
- API usage logs.
- Billing webhook events.

It creates deduplicated active alerts and can create incidents for critical alerts. It does not send external notifications and does not store raw payloads or secrets.

`UptimeReadinessService` checks whether the health/status routes and operational tracking tables are available. This keeps uptime readiness internal and shared-hosting compatible.

STEP34 adds safe console commands:

- `monitoring:health-review`
- `monitoring:incident-review`

Runbooks live under `docs/runbooks` and cover queue failures, provider failures, billing webhook failures, and incident response.

## STEP35 Launch Preparation And Go-Live Checklist

STEP35 adds the final internal launch readiness layer. It remains read-only and does not deploy, provision infrastructure, execute backups, execute restores, create DNS records, or create provider accounts.

`LaunchChecklistService` aggregates launch checks across:

- Infrastructure.
- Security.
- Monitoring.
- Backups.
- Providers.
- Domains.
- Billing.
- Operations.

Checks are classified as blockers, warnings, recommendations, or informational items. The service reuses existing production readiness, backup readiness, and uptime readiness foundations.

`GoLiveStatusService` converts the launch checklist into a simple status:

- `ready`
- `warning`
- `blocked`

`RollbackReadinessService` validates rollback prerequisites, backup readiness, deployment note availability, and restore prerequisite documentation. It is checklist-only and never performs rollback or restore actions.

`system:go-live-status` displays a safe go-live summary with blocker, warning, and recommendation counts.

Deployment guidance lives under `docs/deployment`:

- Shared hosting.
- VPS deployment.
- Queue workers.
- Scheduler.
- Domain onboarding.
- Provider onboarding.

The go-live strategy is conservative: launch only after environment-specific manual verification confirms health routes, admin protection, inbox behavior, provider webhooks, domain pool readiness, backup readiness, and rollback documentation.

## STEP36 Production Deployment And First Live Validation

STEP36 adds a first-live validation package for the first production environment. It is read-only and does not perform deployment automation, SSH operations, DNS changes, provider account setup, backup execution, or rollback execution.

`ProductionEnvironmentValidationService` validates:

- `APP_ENV`
- `APP_DEBUG`
- `APP_KEY`
- Database connectivity.
- Cache readiness.
- Session driver.
- Queue driver.
- Mail configuration placeholder status.
- Filesystem/storage readiness.
- Installer lock status.

`ServerReadinessService` validates writable paths, `bootstrap/cache`, PHP version compatibility, required PHP extensions, scheduler readiness, and queue worker readiness.

`FirstLiveSmokeTestService` validates route-level readiness for homepage, health, status, installer lock behavior, inbox, sitemap, robots, API ping protection, and admin protection without making external HTTP calls.

`system:first-live-check` aggregates environment, server, and smoke checks and outputs only safe status, blocker, and warning messages.

First-live deployment guidance lives in `docs/deployment/first-live-environment.md`, and provider/domain validation guidance lives in `docs/deployment/provider-domain-validation.md`.

## STEP37 Real Provider Sandbox Testing

STEP37 prepares sandbox validation for Mailgun, Postmark, and Amazon SES without enabling live traffic or requiring production provider secrets.

`config/mail-providers.php` includes sandbox options for sandbox enablement, test signature acceptance, allowed providers, replay window, payload logging, observability, and test-only signing keys.

`ProviderSandboxValidationService` validates provider fixtures by simulating signatures, checking invalid signatures, auditing normalization output, creating queue-first intakes, processing storage through the existing mail pipeline, and confirming public inbox visibility.

The service records privacy-safe operations events:

- `sandbox_provider_validated`
- `sandbox_provider_failed`
- `sandbox_signature_rejected`
- `sandbox_duplicate_detected`

`mail:provider-sandbox-check` runs the validation from the console and prints only safe summaries. It does not print full payloads or secrets.

Sandbox fixtures live under `tests/Fixtures/mail-providers` and use deterministic `example.test` addresses, fake message ids, and no personal data.

## STEP38 Live Provider Staging Validation

STEP38 hardens installer enforcement and adds a provider staging readiness layer.

Installed-app enforcement now protects live and staging surfaces before installation is healthy:

- `/login`
- `/register`
- `/dashboard`
- `/admin`
- `/admin/login`
- `/api/*`
- `/billing/*`
- `/inbox`
- Provider webhook endpoints

Incomplete installs redirect browser requests to `/install` and return safe installer-required JSON responses for API/webhook style requests.

`ProviderConnectivityValidationService` validates provider configuration, activation state, webhook route readiness, signing configuration readiness, and intake queue readiness without external HTTP calls or credential exposure.

`StagingReadinessService` aggregates provider readiness, domain readiness, queue readiness, and installer readiness. It records privacy-safe operations events for staging validation start, pass, failure, and provider blockers.

`system:staging-readiness` prints safe blocker, warning, and recommendation summaries for staging validation.

## STEP39 Production Provider Activation

STEP39 adds a production provider activation readiness layer without calling provider APIs or storing provider credentials.

Provider activation states are config-driven:

- `inactive`
- `staging`
- `ready`
- `active`
- `suspended`

`provider_activation_audits` records provider state transitions with previous state, new state, reason, performer label, sanitized metadata, and timestamps. No secrets or credentials are stored.

`ProviderSafetyCheckService` verifies staging readiness, webhook readiness, queue readiness, installer readiness, signing configuration, activation state validity, and idempotency readiness.

`ProviderActivationService` owns readiness summaries and state transitions. It records safe operations events:

- `provider_activation_requested`
- `provider_activation_ready`
- `provider_activation_blocked`
- `provider_activation_completed`
- `provider_activation_suspended`

`provider:activation-status` prints safe provider states, blocker counts, warning counts, and passed check counts.

## STEP40 Real Domain Onboarding

STEP40 adds configuration-only domain onboarding readiness without live DNS lookups, registrar integrations, or DNS automation.

`domains.onboarding_state` stores the lifecycle state: `draft`, `validating`, `ready`, `active`, or `suspended`. `domain_onboarding_audits` records domain lifecycle changes with safe metadata and no credentials or DNS record values.

`DomainDnsReadinessService` resolves manual MX, SPF, DKIM, DMARC, and provider mapping readiness flags. `DomainSafetyCheckService` reviews DNS readiness, domain pool compatibility, provider compatibility, feature gates, and organization compatibility. `DomainOnboardingService` owns lifecycle changes, audit creation, activation review, recommendations, and privacy-safe operations events.

The domain pool assigns only domains with both general status `active` and onboarding state `active`. `domain:onboarding-status` prints aggregate blockers, warnings, recommendations, and lifecycle counts without exposing domain names or DNS values.

## STEP41 First Real Mail Reception Validation

STEP41 adds a read-only first real mail validation layer without external HTTP calls or credential setup.

`FirstRealMailValidationService` checks provider activation, domain onboarding, webhook route readiness, signature configuration, duplicate/replay protection, queue capacity, mailbox generation, inbox visibility, and cleanup compatibility. It records safe operations events for validation start, ready, and blocked states.

`MailReceptionTraceService` traces the lifecycle from provider intake to inbound intake, queued job handoff, email message storage, and public inbox visibility. It supports lookup by intake UUID, provider message id, email message UUID, and mailbox address. Trace output excludes raw provider payloads, raw HTML, secret headers, signature secrets, and storage paths.

`mail:first-real-check` prints safe readiness and optional trace diagnostics for the first real message validation workflow.

## STEP42 Production Load & Stress Validation

STEP42 adds a no-traffic load validation framework for production readiness review.

`ProductionLoadValidationService` evaluates queue readiness, inbox limits, provider intake safety, domain pool filtering, cache readiness, and monitoring readiness. It records safe operations events for `load_validation_started`, `load_validation_ready`, and `load_validation_blocked`.

`StressReadinessService` reviews queue throughput assumptions, cleanup chunk assumptions, inbox polling, provider intake, billing, and operations assumptions. It records `stress_review_completed` or `stress_review_warning`.

`LoadScenarioService` documents scenarios such as inbox creation volume, inbox polling, provider intake, queue backlog, and provider failure checks. Scenarios are documentation-only and never generate load.

`system:load-readiness` prints safe blocker, warning, recommendation, and scenario summaries.

## STEP43 Launch Candidate RC3

STEP43 adds a unified launch certification layer without deploying the application.

`RC3CertificationService` aggregates security, staging, provider, domain, first-real-mail, load, monitoring, go-live, operational, and system foundation readiness. It classifies the candidate as `certified`, `warning`, or `blocked`.

`LaunchBlockerReviewService` normalizes blockers and warnings with severity, ownership, category, and recommendations. `system:rc3-certification` prints a safe certification summary.

RC3 certification records `rc3_certification_started`, `rc3_certification_passed`, `rc3_certification_warning`, and `rc3_certification_blocked` operations events without sensitive metadata.

## STEP44 Public Beta Launch Preparation

STEP44 adds a controlled public beta readiness layer without launching the product.

`PublicBetaReadinessService` reviews onboarding, support, feedback, monitoring, and incident readiness. `SupportReadinessService` checks runbooks, escalation paths, troubleshooting guidance, monitoring, and incident process readiness. `IssueTriageService` classifies severity, ownership, priority, and response guidance. `BetaFeedbackReadinessService` reviews feedback collection, issue intake, and operational response documentation.

`PublicBetaCertificationService` aggregates beta readiness with RC3 certification and classifies the beta candidate as `certified`, `warning`, or `blocked`. `system:public-beta-status` prints safe blocker, warning, recommendation, and certification summaries.
