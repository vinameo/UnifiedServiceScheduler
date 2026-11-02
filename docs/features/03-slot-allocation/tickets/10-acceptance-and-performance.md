# 10: Acceptance and performance

**What to build:** Prove F03 end to end and measure M-03.1 (36 `find()` calls for one day).

**Blocked by:** [09-snapshot-repository-for-one-day](09-snapshot-repository-for-one-day.md)

**Status:** ready-for-agent

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-21

- [ ] TC-03-21 (M-03.1; Unit (`@group performance`)): given Snapshot of 30 technicians, 20 bays, 200 busy intervals; when 36 `find()` calls (08:00…16:45); then Total < 50 ms (5 runs, median). Test: `CandidateFinderPerformanceTest`.
- [ ] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [ ] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [ ] Whole-branch self-review written to `.claude/reviews/feature__03-slot-allocation.md` (the merge hook requires it)
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
