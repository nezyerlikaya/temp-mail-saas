# STEP33 Performance Hardening Report

## Release Status

STEP33 completed as an additive performance and scalability pass. No routes were removed, no features were removed, and no business behavior was intentionally changed.

## Bottlenecks Found

- Public inbox polling depended on single-column indexes for mailbox, status, quarantine, expiration, and recency filters.
- Provider intake lookup and duplicate detection needed composite provider-aware indexes for real webhook volume.
- Operations dashboard used repeated count and sum queries for several widgets.
- Domain selection and assignment history needed better indexed paths for larger domain pools.
- Load readiness did not yet include database, cache, or admin surface checks.

## Optimizations Applied

- Added additive composite indexes for inbox, provider intake, recipients, attachments, cleanup, abuse, operations events, queue metrics, domains, domain assignments, billing subscriptions, billing invoices, billing webhook events, and domain health checks.
- Added `config/performance.php` for cache TTLs, query thresholds, aggregation limits, inbox polling limits, queue thresholds, and domain pool health thresholds.
- Added `PerformanceCacheService` with safe fallback behavior when cache storage is unavailable.
- Tuned the admin operations dashboard to use grouped summary queries and configurable widget limits.
- Extended `LoadReadinessService` with database, cache, queue, provider, cleanup, intake, and admin readiness sections.
- Made public inbox polling limit config-driven while preserving the existing safe output structure.
- Made domain pool health threshold config-driven while preserving fallback domain behavior.

## Tests Added

- Performance cache and invalidation behavior.
- Operations dashboard bounded query path.
- Inbound queue idempotency regression coverage.
- Public inbox list limit and safe output coverage.
- Domain pool performance fallback coverage.
- Expanded load readiness sections.
- Performance configuration safety defaults.

## Remaining Risks

- Exact query plans should still be reviewed against the production database engine after real data grows. SQLite test coverage confirms compatibility, but MySQL or MariaDB plan selection may differ.
- Sync queues remain suitable for shared-hosting bootstrap only. Real inbound webhook volume should use database queue workers at minimum, and Redis on VPS when available.
- File cache is safe for shared hosting, but larger operations dashboards will perform better with Redis or another centralized cache backend.
- Attachment scanning and MIME parsing remain foundations only; real antivirus or advanced content scanning is still out of scope.

## Shared-Hosting Guidance

- Keep queue volumes low or run a scheduled queue worker if supported.
- Use the database queue driver for inbound mail once real providers are enabled.
- Keep dashboard cache TTLs enabled to reduce repeated operations queries.
- Keep inbox polling limits conservative.
- Monitor database size for `email_messages`, `inbound_mail_intakes`, `operations_events`, and `billing_webhook_events`.

## VPS Guidance

- Move queues to Redis when inbound mail volume grows.
- Use Redis cache for operations, readiness, and localization progress summaries.
- Add a real process monitor for queue workers.
- Review slow query logs after loading realistic message, intake, and event volumes.
- Schedule cleanup jobs at off-peak intervals and tune chunk sizes based on observed database latency.

## Recommendation

Proceed to STEP34 Production Operations & Monitoring with a focus on runtime visibility: slow-query tracking, queue worker health, cache hit visibility, and operational alerts.
