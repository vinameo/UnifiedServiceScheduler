# 10: Acceptance and performance

**What to build:** Prove F06 end to end and measure AC-06.16 (p95 < 300 ms).

**Blocked by:** [09-ignore-an-appointment-s-own-slot](09-ignore-an-appointment-s-own-slot.md)

**Status:** done

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-13, TC-06-20

- [x] TC-06-13 (AC-06.16, NFR-02; Integration (`@group performance`)): given 20 bays, 30 technicians, 200 bookings that day; when 30 × `GET availability`; then p95 < 300 ms. Test: `AvailabilityPerformanceTest`.
- [x] TC-06-20 (All; Manual): given Stack running; when M1–M4; then As A2. Test: `Record in A2`.
- [x] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [x] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [x] Whole-branch self-review written to `.claude/reviews/feature__06-alternative-times.md` (the merge hook requires it)
- [x] README §8 checks green; no existing test skipped, deleted or weakened
