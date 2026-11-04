# 04: No-show

**What to build:** Marking a no-show before the start answers 422 `NO_SHOW_BEFORE_START`; after it, 200 with allocations inactive (AC-12.6).

**Blocked by:** [03-check-in-rules](03-check-in-rules.md)

**Status:** done

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-06

- [x] TC-12-06 (AC-12.6; Integration): given Booking at 11:20 (now 10:50); when No-show; then `travel` to 11:20 and no-show; then 422 `NO_SHOW_BEFORE_START`; then 200 `NO_SHOW` with inactive allocations. Test: `…::test_AC_12_6_no_show_only_after_start`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
