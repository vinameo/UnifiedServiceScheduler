# 03: Request outcome counters and /metrics (tracer bullet)

**What to build:** The terminable `RecordRequestOutcome` middleware writes one request log line and raises `metric_counters` per outcome (`booking_requests_total{outcome}` and the others of AC-08.6); `/metrics` exposes them in Prometheus format. A counter write error is only logged (INV-08.4).

**Blocked by:** [02-json-logs-with-context-and-correlation-id](02-json-logs-with-context-and-correlation-id.md)

**Status:** done

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-02, TC-08-06, TC-08-15

- [x] TC-08-02 (AC-08.2; Integration): given No free slot; when `POST /appointments` → 409; then `booking_requests_total{outcome="no_availability"}` +1; other outcomes unchanged. Test: `MetricsTest::test_AC_08_2_no_availability_is_counted`.
- [x] TC-08-06 (AC-08.6, INV-08.3; Integration): given Read counters before; when 201; 422; 500 (test route); a replay with the same key; 412 (F04 `touch`); failed sign-in; one `AllocationRetried`; then `confirmed`, `validation_error`, `error`, `idempotent_replays_total`, `appointment_modified_total`, `sign_in_failures_total`, `allocation_retries_total` each +1; the replay does **not** increase `booking_requests_total`. Test: `MetricsTest::test_AC_08_6_each_counter_moves_once`.
- [x] TC-08-15 (INV-08.4; Integration): given A fake `MetricCounterRepository` that throws; when `POST /appointments`; then 201 as usual; 1 warning log line. Test: `MetricsTest::test_INV_08_4_metrics_failure_does_not_break_request`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
- [x] Run `/code-review` on the branch and fix findings before later tickets build on it
