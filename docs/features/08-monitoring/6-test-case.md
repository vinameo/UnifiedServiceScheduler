# F08 · Monitoring · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Logs and redaction**: run real flows (sign-up, failed sign-in, booking, cancel) with logs captured in memory, then scan every line.
- **Counters**: one test per outcome type; read `metric_counters` before/after.
- **`/metrics` not exposed**: an app-level test (no internal flag) and a manual check through Nginx.
- **Screen**: component tests; one manual run.

## A2. Manual scenarios

| # | Steps | Expected |
| --- | --- | --- |
| M1 | `curl -i http://localhost:8088/metrics` | 404 |
| M2 | `docker compose exec nginx wget -qO- http://localhost:9100/metrics` | Prometheus text with `booking_requests_total` |
| M3 | `docker compose port nginx 9100` | No published port |
| M4 | Book a full slot (409), sign in as admin → Monitoring | "No availability (409)" ≥ 1; a NO_AVAILABILITY row in Recent errors |
| M5 | Copy the correlationId from M4's Network tab, paste it into the search box | Exactly that row |
| M6 | `docker compose stop postgres`; `curl -i localhost:8088/health/ready` | 503 |
| M7 | `docker compose logs php | tail` | JSON lines with `correlationId`; no email/phone number |

## A3. Test data

`World`; the test log channel writes to an in-memory handler (Monolog `TestHandler`).

## A4. Not tested

Sending traces to a real collector (off by default). External dashboards.

## A5. Exit criteria

Every TC green; M1–M7 pass.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-08.1, FR-08.2, INV-08.2 | TC-08-01 |
| AC-08.2, FR-08.3, D-08.6 | TC-08-02 |
| AC-08.3, FR-08.5, INV-08.8 | TC-08-03 |
| AC-08.4, INV-08.6 | TC-08-04 |
| AC-08.5, FR-08.1, INV-08.1, D-08.1, D-08.2 | TC-08-05, TC-08-14 |
| AC-08.6, D-08.5 | TC-08-06 |
| AC-08.7, INV-08.5, D-08.7 | TC-08-07, TC-08-08 |
| AC-08.8, D-08.9 | TC-08-09 |
| AC-08.9 | TC-08-10 |
| AC-08.10, FR-08.4, D-08.8 | TC-08-11 |
| AC-08.11, D-08.4 | TC-08-12 |
| AC-08.12 | TC-08-13 |
| INV-08.3 | TC-08-06 |
| INV-08.4 | TC-08-15 |
| INV-08.7 | TC-08-16 |
| INV-08.9 | TC-03-22 (F03, rerun) |
| D-08.3 | TC-08-01 |
| D-08.10, D-08.11 | TC-08-09 |
| API §B4 | TC-08-17 |

## B2. Test cases

Backend in `api/tests/Feature/Monitoring/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-08-01 | AC-08.1, D-08.3 | Integration | Logs go to `TestHandler` | `GET /api/v1/me` (401) without the header | Header `X-Correlation-Id` = X; exactly 1 `request` log line with `correlationId` X, `status 401`, `durationMs` | Request line missing or different id | `RequestLogTest::test_AC_08_1_response_and_log_share_correlation_id` |
| TC-08-02 | AC-08.2 | Integration | No free slot | `POST /appointments` → 409 | `booking_requests_total{outcome="no_availability"}` +1; other outcomes unchanged | Wrong outcome counted | `MetricsTest::test_AC_08_2_no_availability_is_counted` |
| TC-08-03 | AC-08.3 | Integration | The DB connection points at a host that does not exist (runtime config) | `GET /health/ready` | 503, `checks.database = fail` | Returns 200 | `Feature/Platform/HealthTest::test_AC_08_3_ready_is_503_when_database_is_down` |
| TC-08-04 | AC-08.4 | Integration | Advisor, foreman, customer | `GET /monitoring/summary?range=1h` | 403 for all three; admin 200 | Wrong Gate | `MonitoringApiTest::test_AC_08_4_admin_only` |
| TC-08-05 | AC-08.5, INV-08.1 | Integration | `TestHandler` | Sign-up (email, phone), failed sign-in, booking, cancel (F07), a deliberate 500 (test route) | Every record is `json_encode`-able; each has `correlationId`; none matches an email regex, `\b0\d{9}\b`, `"password"`, `XSRF`, `laravel_session` | Redaction processor missing | `LogRedactionTest::test_AC_08_5_logs_have_no_personal_data` |
| TC-08-06 | AC-08.6, INV-08.3 | Integration | Read counters before | 201; 422; 500 (test route); a replay with the same key; 412 (F04 `touch`); failed sign-in; one `AllocationRetried` | `confirmed`, `validation_error`, `error`, `idempotent_replays_total`, `appointment_modified_total`, `sign_in_failures_total`, `allocation_retries_total` each +1; the replay does **not** increase `booking_requests_total` | Replay counted twice | `MetricsTest::test_AC_08_6_each_counter_moves_once` |
| TC-08-07 | AC-08.7, INV-08.5 | Integration | — | `GET /metrics` without the flag; with `X-Forwarded-For`; with server var `METRICS_INTERNAL=1` | 404; 404; 200 `text/plain` with `# TYPE booking_requests_total counter` | Header trusted | `MetricsEndpointTest::test_AC_08_7_metrics_only_from_internal_nginx` |
| TC-08-08 | AC-08.7, D-08.7 | Manual | Stack running | M1, M2, M3 | As A2 | Port 9100 published | Record in A2 |
| TC-08-09 | AC-08.8, D-08.9–08.11 | Integration | Admin of D1; `api_events` of D1 (2), D2 (3), NULL (1); 2 PENDING outbox rows | `summary?range=24h` | `recentErrors` has 3 rows (D1 + NULL); `outbox.pending 2`; `database.status UP`, `connectionsMax > 0` | D2 leaked | `MonitoringApiTest::test_AC_08_8_summary_is_scoped` |
| TC-08-10 | AC-08.9 | Integration | An error with correlation X | `errors?correlationId=X` | 1 row | Wrong column searched | `…::test_AC_08_9_search_by_correlation_id` |
| TC-08-11 | AC-08.10 | Integration | `InMemoryExporter`, `OTEL_SDK_DISABLED=false` | Book | Spans `http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`; one `traceId`; the last 3 are children of `http.request` | Span missing | `TracingTest::test_AC_08_10_booking_spans` |
| TC-08-12 | AC-08.11 | Integration | `api_events` 8 days and 6 days old | `monitoring:prune` | The 8-day row is deleted; the 6-day row stays | Wrong prune | `PruneTest::test_AC_08_11_events_older_than_7_days_are_pruned` |
| TC-08-13 | AC-08.12 | Web component | Fake API summary 24h | Render, click "7 days" | Card "No availability (409)" "131" "10.5% · business outcome, not an outage"; URL `?range=7d`, API called again with `7d` | Range not in the request | `web/src/staff/monitoring/MonitoringPage.test.tsx` |
| TC-08-14 | D-08.2, R-08.4 | Unit | — | `RedactPersonalData` on: `{"user":{"email":"a@b.c"}}`, message "call 0903 123 218", a UUID, `{"Authorization":"x"}` | email dropped; "call [phone]"; UUID unchanged; Authorization key dropped | Over- or under-redaction | `api/tests/Unit/Support/RedactPersonalDataTest` |
| TC-08-15 | INV-08.4 | Integration | A fake `MetricCounterRepository` that throws | `POST /appointments` | 201 as usual; 1 warning log line | Monitoring failure breaks the request | `MetricsTest::test_INV_08_4_metrics_failure_does_not_break_request` |
| TC-08-16 | INV-08.7 | Integration | A test route throws an exception whose message holds an email | Call it | `api_events.detail` has no email | Raw message stored | `ApiEventsTest::test_INV_08_7_detail_is_redacted` |
| TC-08-17 | API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `MonitoringAdversarialInputTest` |
| TC-08-18 | All | Manual | Stack running | M1–M7 | As A2 | — | Record in A2 |

## B3. Fixtures

- `Tests\Support\Logging\CapturesLogs`: replaces the default channel with a `TestHandler` that still goes through the real processors.
- Test route `/api/v1/_testing/fail` (F04 provider) throws a `RuntimeException` whose message contains an email.

## B4. Regression

`Feature/Platform/*` (CorrelationId, Health, ProblemDetails), all of F02, F04 (the new middleware runs on every request).
