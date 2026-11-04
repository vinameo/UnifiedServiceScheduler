# 03: Check-in rules

**What to build:** Check-in answers 422 `INVALID_STATUS_TRANSITION` from a wrong status, `NOT_SERVICE_DAY` on another day, and `NEEDS_RESCHEDULE` for a booking without bay or technician until it is rescheduled (AC-12.2, AC-12.5, AC-12.14, D-12.2).

**Blocked by:** [02-check-in-start-and-complete](02-check-in-start-and-complete.md)

**Status:** done

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-02, TC-12-05, TC-12-27

- [x] TC-12-02 (AC-12.2; Integration): given COMPLETED booking; when Check in; then 422 `INVALID_STATUS_TRANSITION`. Test: `…::test_AC_12_2_completed_cannot_check_in`.
- [x] TC-12-05 (AC-12.5; Integration): given Booking tomorrow; when Check in today; then 422 `NOT_SERVICE_DAY`. Test: `StatusActionsTest::test_AC_12_5_check_in_only_on_service_day`.
- [x] TC-12-27 (AC-12.14, D-12.2; Integration): given As TC-12-09: Y displaced (`OVERRUN`, 0 allocations); when Advisor checks Y in; then reschedules Y to the same time (a bay is free again) and checks in; then 422 `NEEDS_RESCHEDULE`, Y unchanged; reschedule 200 with 3 active allocations and the flag cleared; check-in 200. Test: `ContactFlagTest::test_AC_12_14_displaced_booking_needs_reschedule_before_check_in`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
