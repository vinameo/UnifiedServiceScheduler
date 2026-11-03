# 07: Next available time

**What to build:** `GET /dealerships/{d}/availability/next?after=` returns the next free start, bounded by the 90-day limit (AC-06.12, INV-06.5, D-06.4, D-06.7).

**Blocked by:** [06-suggestion-chips-in-the-error-card](06-suggestion-chips-in-the-error-card.md)

**Status:** done

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-12, TC-06-21

- [x] TC-06-12 (AC-06.12; Integration): given Monday–Thursday full; Friday 08:00 free; when `availability/next?after=Monday 09:00`; then `nextAvailable.start` = Friday 08:00. Test: `NextAvailableTest::test_AC_06_12_next_free_slot`.
- [x] TC-06-21 (INV-06.5, D-06.4, D-06.7; Integration): given Clock 2026-11-02; day 2027-01-29 (88 days ahead) full; 2027-02-01 free; when `POST` for 2027-01-29 09:00; `availability/next?after=2027-01-29T09:00+07:00`; then `alternatives` hold nothing after 2027-01-31; `next` is `null` or ≤ 2027-01-31. Test: `AlternativesOnConflictTest::test_INV_06_5_suggestions_stay_within_the_horizon`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened

