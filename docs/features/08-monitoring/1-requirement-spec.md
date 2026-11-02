# F08 · Monitoring · Requirement spec (RQ)

Branch: `feature/08-monitoring` · Priority: Should · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** When something goes wrong, the team must answer "what happened to this request?" and tell "the system is broken" apart from "the workshop is full". A 409 for no availability is a normal business outcome; a 5xx is an incident.

**What it does.**
- JSON logs, every line with correlation id, dealership, user, appointment and outcome; **never** passwords, tokens, phone numbers or emails.
- Every request has `X-Correlation-Id` (already exists), with the same value in the logs and in errors.
- Metrics for bookings by outcome, booking duration, pair retries, idempotent replays, 412s and failed sign-ins; exposed at `/metrics` on the internal network only.
- OpenTelemetry traces for the 4 steps of a booking.
- An admin "System monitoring" screen: app, DB and outbox health; booking figures for 1 hour/24 hours/7 days; recent errors; search by correlation id.

**Who uses it.** Admins (screen); monitoring systems (Prometheus, an OTel collector) through the internal network.

**What it does not do.** Automatic alerts (email/Slack), Grafana dashboards, long-term log storage.

## A2. Usage steps

1. Admin → Administration → Monitoring → "24 hours".
2. Sees "No availability (409) 131 · business outcome, not an outage" next to "System errors (5xx) 15".
3. A customer reports an error with a code → paste it into "Search by correlationId" → the error row shows.

## A3. Screens and deviations from the drafts

Draft: `Admin/11-system-monitoring.png`.

| Draft | Spec | Reason |
| --- | --- | --- |
| "Figures on this screen are sample data." | Removed; real figures | — |
| "Application · Running · 12 ms response" | "Running · p95 {n} ms (bookings)" | The real measure available is booking duration |
| "Outbox pending … View" | The "View" button appears once F11 has its page | Build order |
| Monitoring menu item visible to advisors | Admin only | AC-08.4 |

## A4. Key rules in plain words

- Only admins see the monitoring screen.
- Admins see their dealership's figures and errors, plus system errors with no dealership.
- `/metrics` is never open to the Internet.
- Logs contain no personal data.

## A5. Out of scope

Alerts, central log storage, tracing outside the booking flow.

## A6. Success criteria

- From a correlation id, find the log line and the error row on screen in < 1 minute.
- Scanning the logs after the full test run: 0 emails, 0 phone numbers, 0 passwords (M-08.1).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-08.1 | Logs: default channel `stderr`, formatter `Monolog\Formatter\JsonFormatter`; a processor adds `correlationId`, `userId`, `dealershipId`, `appointmentId`, `outcome`; a processor removes personal data (D-08.2). |
| D-08.2 | Redaction: drop every context key matching `password|token|secret|authorization|cookie|phone|email` (case-insensitive, nested too); replace email-like strings with `[email]` and phone-like strings with `[phone]` in `message` and string values. A phone-like string is 9–15 digits, optionally starting with `+`, separated only by single spaces, dots or dashes, and **not** touching a letter, digit, `-` or `_` on either side (regex `(?<![\w-])\+?\d(?:[ .-]?\d){8,14}(?![\w-])`). Values of keys ending in `Id`/`_id`, plus `correlationId`, `reference`, `route` and `occurredAt`, are never rewritten: ids (UUID v7 such as `01935512-7712-…`) and references (`APT-20261102-0042`) must stay in the logs (`CLAUDE.md`). |
| D-08.3 | Each request writes exactly one "request" log line when it ends (`method`, `route`, `status`, `code`, `outcome`, `durationMs`). |
| D-08.4 | Table `api_events` stores every `POST /appointments` (for counting) and every response ≥ 400 (for the error list), plus outbox `DELIVERY_FAILED` errors (written by F11). Kept 7 days, `monitoring:prune` daily. |
| D-08.5 | Prometheus counters are monotonic counters stored in a `metric_counters` table (never PHP-FPM memory, `CLAUDE.md`), incremented with `INSERT … ON CONFLICT DO UPDATE SET value = value + :n` after the response was sent (`terminate`). |
| D-08.6 | Booking `outcome`: 201 → `confirmed`; 409 `NO_AVAILABILITY` → `no_availability`; other 4xx → `validation_error`; 5xx → `error`. Replays are not counted in `booking_requests_total`, only in `idempotent_replays_total`. |
| D-08.7 | `/metrics`: a route without sign-in, but with a middleware that lets it through only when the internal Nginx sets `fastcgi_param METRICS_INTERNAL 1`; otherwise 404. Add an internal Nginx server on port 9100, not published in compose. The public Nginx already returns 404 for `/metrics`. |
| D-08.8 | Tracing: add the packages `open-telemetry/sdk` and `open-telemetry/exporter-otlp` (approved by default; README D-G.11). Default `OTEL_SDK_DISABLED=true` (nothing is sent). Spans: `http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`. Seam `App\Support\Tracing\Tracer` so the domain does not depend on the SDK. |
| D-08.9 | Screen: admin only; data scope = the admin's `dealership_id` or `NULL`. Auto refresh every 30 seconds. |
| D-08.10 | DB card: `UP` when `select 1` succeeds; "{used}/{max} connections" from `pg_stat_activity` and `max_connections`. |
| D-08.11 | Outbox card: `PENDING` count and the age of the oldest row (reads `outbox_messages`, which exists since F04). |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-08.a | Send traces to a collector in compose? | No; enable through env when a collector exists. |
| Q-08.b | Store every request (including 2xx reads) in `api_events`? | No (D-08.4), to keep the table small. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Exists from the start: `CorrelationId` middleware (`api/app/Http/Middleware/CorrelationId.php`, `Log::shareContext`), `HealthController` (`/health/live`, `/health/ready` → 503 when the DB fails), `ProblemRenderer` (adds `correlationId` to the body), Nginx blocking public `/metrics` (`docker/nginx/snippets/app.conf`), `config/logging.php` defaulting to `stack` → `single` (file, not JSON). From F04: events `AllocationRetried`, `IdempotentReplayed`, `AppointmentModifiedDetected`; table `outbox_messages`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-08.1 | JSON logs + context processor + personal-data redaction processor. |
| S-08.2 | Middleware `RecordRequestOutcome` (terminable): request log line, `api_events`, counters. |
| S-08.3 | Tables `api_events`, `metric_counters`; command `monitoring:prune`. |
| S-08.4 | `/metrics` + guard + internal Nginx server. |
| S-08.5 | Tracing (packages + `Tracer` seam + 4 spans). |
| S-08.6 | `GET /monitoring/summary`, `GET /monitoring/errors`. |
| S-08.7 | System monitoring screen. |

## B3. Invariants

| ID | Invariant | Checked where |
| --- | --- | --- |
| INV-08.1 | No log line contains a password, token, email or phone number (FR-08.1, NFR-05). | The D-08.2 processor runs for every channel; a TC scans the logs. |
| INV-08.2 | Every response has `X-Correlation-Id`; every log line of that request has the same `correlationId` (FR-08.2). | Existing `CorrelationId` (prepended) + `Log::shareContext`. |
| INV-08.3 | Counters never decrease (unless the DB is wiped); never stored in worker memory. | `metric_counters`, only `UPDATE value = value + n`. |
| INV-08.4 | Recording metrics never slows down or breaks a request: it runs in `terminate()`, and write errors are only logged (never thrown). | `RecordRequestOutcome::terminate`. |
| INV-08.5 | `/metrics` returns 404 for every request that does not come through the internal Nginx (FR-08.5). | Middleware `EnsureInternalMetrics` + Nginx. |
| INV-08.6 | Admins see only `api_events` with their `dealership_id` or `NULL`; other roles 403 (AC-08.4). | Gate `admin`; `WHERE` in the repository. |
| INV-08.7 | `api_events.detail` holds no personal data (the D-08.2 redaction applies to detail). | On write. |
| INV-08.8 | `/health/ready` returns 503 when the DB does not answer (FR-08.5, AC-08.3). | Existing; F08 adds a DB-down test. |
| INV-08.9 | `App\Domain\Allocation` does not depend on tracing (INV-03.1); spans wrap calls from `Booking`. | TC-03-22 stays green. |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/config/logging.php`, `api/.env.example` | `LOG_CHANNEL=stderr`, `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter`; a tap adds the processors. |
| `api/app/Support/Logging/{ContextProcessor,RedactPersonalData}.php` | D-08.1, D-08.2. |
| `api/app/Http/Middleware/RecordRequestOutcome.php` | D-08.3–08.6; registered globally after `CorrelationId`. |
| `api/app/Support/Monitoring/{RequestMetrics,MetricCounterRepository,ApiEventRepository}.php` | Collect F04 events during the request (`scoped` binding), write on terminate. |
| `api/app/Listeners/CollectBookingSignals.php` | Listens to the F04 events. |
| `api/database/migrations/2026_11_08_*` | `api_events`, `metric_counters`. |
| `api/routes/health.php`, `api/app/Http/Controllers/MetricsController.php`, `api/app/Http/Middleware/EnsureInternalMetrics.php` | D-08.7. |
| `docker/nginx/default.conf`, `docker/nginx/production.conf` | Internal server `listen 9100;` with only `location = /metrics`. |
| `api/composer.json` | D-08.8 (2 packages). |
| `api/app/Support/Tracing/{Tracer,OtelTracer,NullTracer}.php`, `api/app/Providers/TracingServiceProvider.php` | D-08.8. |
| `api/app/Domain/Booking/Services/BookingService.php` (`create`) | Wrap 3 steps with `Tracer::span`. |
| `api/app/Http/Controllers/Api/V1/MonitoringController.php`, `Resources/{MonitoringSummary,ApiEvent}Resource.php` | API. |
| `api/routes/console.php` | `monitoring:prune` daily. |
| `web/src/staff/monitoring/*` | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

```php
// illustrative — INV-08.9
interface Tracer { public function span(string $name, callable $fn, array $attributes = []): mixed; }
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `RedactPersonalData` | F11 (outbox `last_error`) |
| `ApiEventRepository::record()` | F11 writes `DELIVERY_FAILED` |
| `Tracer` | F09, F12 (MAY add spans) |
| `CursorPaginator` (F05) | `GET /monitoring/errors` |

## B7. Algorithm (`RecordRequestOutcome`)

1. `handle`: record `startedAt` (hrtime); `$next`.
2. `terminate(request, response)`:
   1. `durationMs`, `route` (route pattern, for example `POST api/v1/appointments`), `status`, `code` (read from the problem body when `Content-Type` is problem+json).
   2. Write one `request` log line (D-08.3).
   3. For `POST /appointments`: replay → `idempotent_replays_total += 1`; not a replay → `booking_requests_total{outcome}` += 1, histogram `booking_duration_seconds`.
   4. `allocation_retries_total += number of AllocationRetried in this request`.
   5. `code = APPOINTMENT_MODIFIED` → `appointment_modified_total += 1`.
   6. A sign-in route with `code ∈ {INVALID_CREDENTIALS, TOO_MANY_ATTEMPTS}` → `sign_in_failures_total += 1`.
   7. `POST /appointments` or `status ≥ 400` → insert into `api_events`.
   8. Any error in steps 2–7 → `Log::warning` (never thrown).

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| `range` not `1h|24h|7d`; malformed `correlationId` | 400 | `VALIDATION_ERROR` |
| No session | 401 | `UNAUTHENTICATED` |
| Not an admin | 403 | `FORBIDDEN` |
| `/metrics` from outside | 404 | `NOT_FOUND` |
| DB down (`/health/ready`) | 503 | (health JSON, not a problem) |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-08.1 | The client sends no `X-Correlation-Id` | Call any API | The response has `X-Correlation-Id`; the log has the same value | TC-08-01 |
| AC-08.2 | No free slot | A booking returns 409 | `booking_requests_total{outcome="no_availability"}` goes up by 1 | TC-08-02 |
| AC-08.3 | The DB is down | `/health/ready` | 503 | TC-08-03 |
| AC-08.4 | An advisor is signed in | Opens System monitoring | 403 `FORBIDDEN` (admin only) | TC-08-04 |
| AC-08.5 (new) | — | Run sign-up, a failed sign-in, a booking, a cancel | Every log line is valid JSON with `correlationId`; no email, phone number or password | TC-08-05 |
| AC-08.6 (new) | — | 201, 422, 500, a replay, 412, a failed sign-in, one pair retry | Each matching counter goes up by exactly 1 | TC-08-06 |
| AC-08.7 (new) | — | `GET /metrics` through the public Nginx; directly to the app without the internal flag; through the internal Nginx | 404; 404; 200 in Prometheus format | TC-08-07, TC-08-08 |
| AC-08.8 (new) | Admin of D1; errors of D1, D2 and with no dealership | `GET /monitoring/summary?range=24h` | Counts and recent errors only from D1 and no-dealership rows | TC-08-09 |
| AC-08.9 (new) | An error with correlation id X | `GET /monitoring/errors?correlationId=X` | Exactly that row | TC-08-10 |
| AC-08.10 (new) | In-memory exporter | Book | 4 spans with the right names, one trace, child spans inside `http.request` | TC-08-11 |
| AC-08.11 (new) | An `api_events` row 8 days old | `monitoring:prune` | Deleted; a 6-day-old row stays | TC-08-12 |
| AC-08.12 (new) | Admin opens System monitoring | Screen | Health cards, booking figure cards, error table; 1 hour/24 hours/7 days changes the figures | TC-08-13 |

## B10. Implementation order

1. Ticket 01 (prefactor): redaction processor + JSON logs; no API change.
2. Tracer bullet: `RecordRequestOutcome` + `metric_counters` + `/metrics` + AC-08.2, then `/code-review`.
3. `api_events` + summary/errors API + screen.
4. Tracing.
5. Acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `docker compose up` does not expose port 9100 to the host.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-08.1 | `terminate()` does not run when PHP-FPM is killed | One data point lost; accepted |
| R-08.2 | Writing `metric_counters` on every request creates hot-row contention | Short upserts outside business transactions; measure; if needed, aggregate per minute |
| R-08.3 | OTel packages incompatible with PHP 8.4/Laravel 13 | `Tracer` seam + `NullTracer`; if the package fails, keep `NullTracer` and record it |
| R-08.4 | The phone regex wipes ids, references or dates | Id-like keys are skipped and the regex needs a non-word boundary on both sides (D-08.2); TC-08-14 covers a reference, an all-digit-prefix UUID v7 and an ISO time |
