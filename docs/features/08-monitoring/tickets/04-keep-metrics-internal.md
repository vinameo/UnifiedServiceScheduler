# 04: Keep /metrics internal

**What to build:** `/metrics` answers 404 unless the request comes through the internal Nginx server (`METRICS_INTERNAL`), whatever `X-Forwarded-For` says; the public Nginx blocks it (AC-08.7, INV-08.5, D-08.7).

**Blocked by:** [03-request-outcome-counters-and-metrics](03-request-outcome-counters-and-metrics.md)

**Status:** done

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); `CLAUDE.md` §Nginx and PHP-FPM; [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-07, TC-08-08

- [x] TC-08-07 (AC-08.7, INV-08.5; Integration): when `GET /metrics` without the flag; with `X-Forwarded-For`; with server var `METRICS_INTERNAL=1`; then 404; 404; 200 `text/plain` with `# TYPE booking_requests_total counter`. Test: `MetricsEndpointTest::test_AC_08_7_metrics_only_from_internal_nginx`.
- [x] TC-08-08 (AC-08.7, D-08.7; Manual): given Stack running; when M1, M2, M3; then As A2. Test: `Record in A2`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
