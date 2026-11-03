# 11: Acceptance

**What to build:** Prove F00 end to end.

**Blocked by:** [10-sign-out-global-401-handling-and-role-gates](10-sign-out-global-401-handling-and-role-gates.md)

**Status:** done

**Branch:** `feature/00-accounts-and-sign-in`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-00-28

- [x] TC-00-28 (All; Manual): given Stack running via `docker compose up`; when M1–M8 (Part A); then As table A2. Test: `Record in table A2`.
- [x] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [x] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [x] Whole-branch self-review written to `.claude/reviews/feature__00-accounts-and-sign-in.md` (the merge hook requires it)
- [x] README §8 checks green; no existing test skipped, deleted or weakened
