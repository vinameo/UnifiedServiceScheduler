# 10: Acceptance

**What to build:** Prove F09 end to end.

**Blocked by:** [09-parallel-changes-and-adversarial-input](09-parallel-changes-and-adversarial-input.md)

**Status:** done

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-23

- [x] TC-09-23 (All; Manual): given Stack running; when M1–M6; then As A2. Test: `Record in A2`.
- [x] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [x] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [x] Whole-branch self-review written to `.claude/reviews/feature__09-reschedule.md` (the merge hook requires it)
- [x] README §8 checks green; no existing test skipped, deleted or weakened
