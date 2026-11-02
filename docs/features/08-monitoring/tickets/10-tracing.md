# 10: Tracing

**What to build:** Add the OpenTelemetry packages (D-G.11) behind a `Tracer` seam and the 4 spans of a booking (`http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`) in one trace (AC-08.10).

**Blocked by:** [09-system-monitoring-screen](09-system-monitoring-screen.md)

**Status:** ready-for-agent

**Branch:** `feature/08-monitoring`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-08-11

- [ ] TC-08-11 (AC-08.10; Integration): given `InMemoryExporter`, `OTEL_SDK_DISABLED=false`; when Book; then Spans `http.request`, `booking.validate`, `allocation.find_candidates`, `booking.write`; one `traceId`; the last 3 are children of `http.request`. Test: `TracingTest::test_AC_08_10_booking_spans`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
