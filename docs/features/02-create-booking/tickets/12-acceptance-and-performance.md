# 12: Acceptance and performance

**What to build:** Prove F02 end to end and measure M-02.1 (NFR-02).

**Blocked by:** [11-customer-booking-screens](11-customer-booking-screens.md)

**Status:** ready-for-agent

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-30, TC-02-33

- [ ] TC-02-30 (NFR-02, M-02.1; Integration (`@group performance`)): given Dealership with 20 bays, 30 technicians, 200 bookings that day; when 50 sequential `POST /appointments` (different times); then p95 < 300 ms. Test: `CreateBookingPerformanceTest`.
- [ ] TC-02-33 (All; Manual): given Stack running; when M1–M9; then As A2. Test: `Record in A2`.
- [ ] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [ ] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [ ] Whole-branch self-review written to `.claude/reviews/feature__02-create-booking.md` (the merge hook requires it)
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
