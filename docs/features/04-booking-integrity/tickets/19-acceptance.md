# 19: Acceptance

**What to build:** Prove F04 end to end.

**Blocked by:** [18-web-appointmentmodifiedalert](18-web-appointmentmodifiedalert.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [BA](../2-business-analytics-spec.md) §B2 metrics; [DB](../3-database-spec.md); [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** none of its own; existing tests prove it changes nothing.

- [x] Every `TC-` row of `6-test-case.md` is automated and green, or run by hand with the result recorded in `6-test-case.md`
- [x] BA metrics (`2-business-analytics-spec.md` §B2) measured and recorded
- [x] Whole-branch self-review written to `.claude/reviews/feature__04-booking-integrity.md` (the merge hook requires it)
- [x] README §8 checks green; no existing test skipped, deleted or weakened
