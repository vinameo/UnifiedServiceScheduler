# 12: Acceptance

**What to build:** Prove F12 end to end, including the adversarial input table of API §B4.

**Blocked by:** [11-bookings-that-need-contact](11-bookings-that-need-contact.md)

**Status:** done

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-23, TC-12-26

- [x] TC-12-23 (API §B4; Integration): when Each row of the adversarial input table; then As the table. Test: `WorkshopAdversarialInputTest`.
- [x] TC-12-26 (All; Manual): given Stack running; when M1–M7; then As A2. Test: `Record in A2`.
- [x] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [x] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [x] Whole-branch self-review written to `.claude/reviews/feature__12-day-of-service.md` (the merge hook requires it)
- [x] README §8 checks green; no existing test skipped, deleted or weakened
