# 11: Acceptance

**What to build:** Prove F10 end to end.

**Blocked by:** [10-expire-and-confirm-race](10-expire-and-confirm-race.md)

**Status:** ready-for-agent

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-20

- [ ] TC-10-20 (All; Manual): given Stack running; when M1–M6; then As A2. Test: `Record in A2`.
- [ ] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [ ] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [ ] Whole-branch self-review written to `.claude/reviews/feature__10-temporary-hold.md` (the merge hook requires it)
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
