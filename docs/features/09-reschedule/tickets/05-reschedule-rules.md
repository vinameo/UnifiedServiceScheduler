# 05: Reschedule rules

**What to build:** Only reschedulable statuses move (422 `INVALID_STATUS_TRANSITION`), the same instant answers 422 `RESCHEDULE_SAME_TIME`, and the new start follows `BookingTimeRules` (AC-09.7, AC-09.9, INV-09.7).

**Blocked by:** [04-version-and-retry-rules-on-reschedule](04-version-and-retry-rules-on-reschedule.md)

**Status:** ready-for-agent

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-09, TC-09-11, TC-09-13

- [ ] TC-09-09 (AC-09.7; Integration): given Bookings in `HELD`, `CHECKED_IN`, `CANCELLED`, `COMPLETED`; when Reschedule each; then 422 `INVALID_STATUS_TRANSITION` with `currentStatus`. Test: `…::test_AC_09_7_only_confirmed_moves` (data provider).
- [ ] TC-09-11 (AC-09.9; Integration): given X at 09:00+07; when `newStart` = `2026-11-02T02:00:00Z` (same instant); then 422 `RESCHEDULE_SAME_TIME`. Test: `…::test_AC_09_9_same_time_is_rejected`.
- [ ] TC-09-13 (INV-09.7; Integration): when `newStart` in the past; Sunday; 91 days ahead; 16:30 for 60 min; then 422 `START_IN_PAST`, `OUTSIDE_OPENING_HOURS`, `START_TOO_FAR_AHEAD`, `OUTSIDE_OPENING_HOURS`. Test: `RescheduleTest::test_INV_09_7_time_rules_apply`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
