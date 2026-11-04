# Feature Review · `feature/08-monitoring`

Date: 2026-11-04  
Branch: `feature/08-monitoring`  
Spec: `docs/features/08-monitoring/`

---

## 1. Branch Review Overview

- **Diff Overview:** `git diff develop...HEAD` across 89 files (+6,003 / -98 lines).
- **Tickets Executed (01–11):**
  1. `01-prefactor-personal-data-redaction-processor`: Implemented `RedactPersonalData` processor and sanitization logic dropping sensitive context keys (`password`, `token`, `secret`, `authorization`, `cookie`, `phone`, `email`) and regex-masking email/phone patterns while preserving UUIDs, references (`APT-...`), route names, and ISO timestamps.
  2. `02-json-logs-with-context-and-correlation-id`: Configured `stderr` JSON logging with `ContextProcessor` enriching records with `correlationId`, `userId`, `dealershipId`, `appointmentId`, `outcome`, and `durationMs`.
  3. `03-request-outcome-counters-and-metrics`: Created `metric_counters` table and repository/service architecture, tracking `booking_requests_total` (`confirmed`, `no_availability`, `validation_error`, `error`), duration histograms, retries, replays, 412s, and failed logins.
  4. `04-keep-metrics-internal`: Implemented `/metrics` endpoint guarded by `EnsureInternalMetrics` middleware requiring `fastcgi_param METRICS_INTERNAL 1`; configured internal Nginx server on port 9100 without host exposure; verified public port 8088 returns 404.
  5. `05-readiness-check`: Verified `/health/ready` returns 503 `{"status":"fail","checks":{"database":"fail"}}` when PostgreSQL is unavailable without requiring a session.
  6. `06-api-events`: Created `api_events` table and repository/service to record booking requests and all $\ge 400$ responses with sanitized details.
  7. `07-monitoring-summary-and-errors-api`: Implemented `GET /api/v1/monitoring/summary` and `GET /api/v1/monitoring/errors`, secured via Gate allowing only `ADMIN`, scoped to the admin's dealership and system-level events (`dealership_id IS NULL`).
  8. `08-prune-monitoring-data`: Created `monitoring:prune` console command with daily schedule to purge `api_events` older than 7 days.
  9. `09-system-monitoring-screen`: Built admin System Monitoring UI in React + Mantine 9 with 3 health cards, 7 metric cards, recent errors table with correlation ID search and copy-to-clipboard, and time-range filtering (1h, 24h, 7d).
  10. `10-tracing`: Integrated OpenTelemetry tracing behind `Tracer` seam with `NullTracer` fallback when disabled, creating 4 booking spans (`http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`) without violating domain purity.
  11. `11-acceptance`: Executed manual scenarios M1–M7 (`TC-08-18`), measured BA metrics M-08.1 through M-08.4, verified test suite (493 PHP tests, 188 Vitest tests, Pint, and PHPStan clean).

---

## 2. Spec Completeness

- **All Acceptance Criteria (AC-08.1 to AC-08.12):**
  - **AC-08.1 / FR-08.2 / INV-08.2:** Request log lines and response headers share the same `X-Correlation-Id` (`RequestLogTest::test_AC_08_1_response_and_log_share_correlation_id`).
  - **AC-08.2 / FR-08.3 / D-08.6:** Booking conflict (409) increments `booking_requests_total{outcome="no_availability"}` by 1 without altering other outcome counters (`MetricsTest::test_AC_08_2_no_availability_is_counted`).
  - **AC-08.3 / FR-08.5 / INV-08.8:** When database is down, `/health/ready` returns 503 with database check failure (`HealthTest::test_AC_08_3_ready_is_503_when_database_is_down`).
  - **AC-08.4 / INV-08.6:** Non-admin roles (Customer, Advisor, Foreman) receive 403 on monitoring summary/errors endpoints; Admin receives 200 (`MonitoringApiTest::test_AC_08_4_admin_only`).
  - **AC-08.5 / FR-08.1 / INV-08.1:** Full request lifecycles (sign-up, failed sign-in, booking, cancel, 500 error) generate valid JSON log lines containing `correlationId` and zero unredacted personal data (`LogRedactionTest::test_AC_08_5_logs_have_no_personal_data`).
  - **AC-08.6 / INV-08.3:** Outcome counters move monotonically by exactly 1 per matched event; idempotent replays increment replay counter without inflating booking totals (`MetricsTest::test_AC_08_6_each_counter_moves_once`).
  - **AC-08.7 / INV-08.5 / D-08.7:** `/metrics` returns 404 from public interface, ignores client-spoofed headers, and serves Prometheus format when accessed internally (`MetricsEndpointTest::test_AC_08_7_metrics_only_from_internal_nginx`).
  - **AC-08.8 / D-08.9–D-08.11:** Admin summary is scoped to user's dealership plus unassigned system events, without leaking foreign dealership data (`MonitoringApiTest::test_AC_08_8_summary_is_scoped`).
  - **AC-08.9:** Filtering errors by correlation ID returns exactly the matching record (`MonitoringApiTest::test_AC_08_9_search_by_correlation_id`).
  - **AC-08.10 / FR-08.4 / D-08.8:** OpenTelemetry spans (`http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`) are generated under one trace with proper child parentage (`TracingTest::test_AC_08_10_booking_spans`).
  - **AC-08.11 / D-08.4:** Console command `monitoring:prune` deletes `api_events` older than 7 days while keeping newer records intact (`PruneTest::test_AC_08_11_events_older_than_7_days_are_pruned`).
  - **AC-08.12 / WEB-08.1:** Admin UI renders health cards, booking metric cards (separating 409 business outcomes from outages), and recent error table with time range selection (`MonitoringPage.test.tsx`).

- **Invariants Enforced (INV-08.1 to INV-08.9):**
  - **INV-08.1:** Personal data redaction runs across log channels and processors.
  - **INV-08.2:** Correlation ID consistently propagated across responses, logs, problem responses, and telemetry.
  - **INV-08.3:** Counters never decrease; updated atomically via PostgreSQL `ON CONFLICT DO UPDATE SET value = value + excluded.value`.
  - **INV-08.4:** Telemetry failures in `terminate()` never abort or degrade client HTTP responses.
  - **INV-08.5:** `/metrics` returns 404 from external networks; isolated via internal Nginx port 9100.
  - **INV-08.6:** Multi-tenant scoping strictly enforced in repository queries.
  - **INV-08.7:** Telemetry error details in `api_events` are sanitized prior to database persistence.
  - **INV-08.8:** Readiness endpoint checks real database connectivity.
  - **INV-08.9:** `Allocation` module remains pure PHP; tracing wrappers reside in domain service orchestration.

- **Business Analytics Metrics (§B2):**
  - **M-08.1:** Log lines with personal data: 0 matches (Threshold: 0) — PASS.
  - **M-08.2:** Retry rate: 0.00% (Threshold: $\le$ 5%/hour) — PASS.
  - **M-08.3:** Booking duration p95: 100.3 ms (Threshold: < 300 ms) — PASS.
  - **M-08.4:** 5xx errors per hour: 0 — PASS.

---

## 3. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `RedactPersonalData`: Focused exclusively on identifying and redacting sensitive PII from string and array structures.
  - `ContextProcessor`: Responsible solely for extracting request and execution context into structured log entries.
  - `RecordRequestOutcome`: Middleware orchestrating termination metrics and API event recording without business logic.
  - `MetricCounterRepository` & `ApiEventRepository`: Abstract data access for counter upserts and time-bounded telemetry queries.
  - `MonitoringPage`: Web presentation decomposed into modular sections (HealthCards, MetricCards, ErrorSearch, ErrorTable).
- **Open/Closed Principle (OCP):**
  - Tracing architecture leverages `TracerInterface` allowing pluggable telemetry backends (OtelTracer, NullTracer, future collectors) without modifying client code.
- **Liskov Substitution Principle (LSP):**
  - `NullTracer` and `OtelTracer` satisfy `TracerInterface` contracts identically without behavioral divergence.
- **Interface Segregation Principle (ISP):**
  - `MetricCounterRepositoryInterface` and `ApiEventRepositoryInterface` expose granular methods tailored to specific read and record workflows.
- **Dependency Inversion Principle (DIP):**
  - All controllers, commands, and middleware depend on abstract repository and service interfaces registered in `DomainServiceProvider`.

---

## 4. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - Monitoring endpoints (`/monitoring/summary`, `/monitoring/errors`) guarded by `auth:sanctum` and Gate `admin` check. Non-admin staff and customers receive 403 `FORBIDDEN`.
  - Multi-tenant scoping ensures admin only sees errors belonging to their own dealership or global unassigned system errors (`dealership_id IS NULL`).
- **A02:2021 — Cryptographic Failures:**
  - Sensitive authentication tokens and passwords are redacted from all log streams and telemetry tables before emission.
- **A03:2021 — Injection:**
  - SQL queries use parameter binding and Eloquent query builder. Database statistics queries use sanitized bindings.
- **A04:2021 — Insecure Design:**
  - Metrics endpoint (`/metrics`) is never exposed on the public HTTP server (returns 404). Handled via internal Nginx listener on port 9100 with internal header validation (`fastcgi_param METRICS_INTERNAL 1`).
- **A05:2021 — Security Misconfiguration:**
  - Standard RFC 9457 Problem Details formats returned on errors with sanitized titles; no internal stack traces leaked.
- **A06:2021 — Vulnerable and Outdated Components:**
  - OpenTelemetry packages pinned to stable versions (`open-telemetry/sdk: ^1.1`, `open-telemetry/exporter-otlp: ^1.1`).
- **A07:2021 — Identification and Authentication Failures:**
  - Failed sign-in attempts tracked via `sign_in_failures_total` counter to monitor credential stuffing attempts.

---

## 5. Extensibility against Future Constraints

- **FC-08.1 (Alerting integration):**
  - `api_events` and `metric_counters` provide durable data stores from which future webhook or alerting relay jobs can evaluate thresholds.
- **FC-08.2 (System-wide admin across dealerships):**
  - Scoping logic in `ApiEventRepository` isolates dealership filtering to single query methods, enabling straightforward role expansion for multi-dealership foremen or global admins.
- **FC-08.3 (High traffic aggregation):**
  - Atomic counter upserts (`ON CONFLICT DO UPDATE`) can transition to batch-buffered increments without affecting repository interfaces.

---

## 6. Design Pass Checks

1. **One decision, one seam:** Logging redaction centralized in `RedactPersonalData`; metrics isolation controlled by `EnsureInternalMetrics` middleware; tracing abstracted by `TracerInterface`.
2. **Read once, decide on that row:** Counter updates executed using atomic upsert queries in PostgreSQL; summary aggregation uses single SQL window expressions.
3. **Bounded:** `range` parameter restricted to predefined enums (`1h`, `24h`, `7d`); error query cursor-paginated with max limit of 50 records.
4. **Adversarial inputs:** Validated in `MonitoringAdversarialInputTest` against invalid ranges, malformed correlation IDs, and negative pagination limits.
5. **Migrations meet real data:** `metric_counters` and `api_events` tables created with appropriate indexes (`occurred_at`, `dealership_id`, `correlation_id`) supporting high-performance queries.
6. **Extract before you copy:** Shared copy strings placed in `copy.ts`; formatters centralized in `format.ts` and `time.ts`.
7. **Copy from one place:** All status labels, card titles, and tooltips imported from module copy definitions.

---

## 7. Findings & Verification Verdict

- **Automated Tests:**
  - Backend: 493 tests passed (4,414 assertions), 0 failures.
  - Frontend: 43 test suites passed, 188 tests passed, 0 failures.
  - Linting & Static Analysis: Pint passed (358 files); PHPStan clean (Level 6, 0 errors); ESLint and TypeScript check clean.
- **Manual Scenarios (M1–M7):** All verified PASS in `6-test-case.md` §A2.
- **Verdict:** **READY TO MERGE** into `develop`.
