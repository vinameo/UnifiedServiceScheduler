# 04: Booking time rules on create

**What to build:** `POST /appointments` refuses a start in the past (`START_IN_PAST`), off the 15-minute step or without an offset (400), more than 90 days ahead (`START_TOO_FAR_AHEAD`), or ending after closing time or on a closed day (`OUTSIDE_OPENING_HOURS` with `latestStart`), all judged in the dealership's time zone (AC-02.2, 02.3, 02.11–02.13, INV-02.4).

**Blocked by:** [03-parallel-bookings-and-replays-through-the-real-endpoint](03-parallel-bookings-and-replays-through-the-real-endpoint.md)

**Status:** ready-for-agent

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-02, TC-02-03, TC-02-12, TC-02-13, TC-02-14, TC-02-15, TC-02-16

- [ ] TC-02-02 (AC-02.2; Integration): given Clock 10:00; when Book 09:00 the same day; then 422 `START_IN_PAST`. Test: `…::test_AC_02_2_start_in_past`.
- [ ] TC-02-03 (AC-02.3; Integration): given Closes 17:00; when OIL at 16:30; then 422 `OUTSIDE_OPENING_HOURS`, `closesAt "17:00"`, `latestStart …T16:00:00+07:00`. Test: `…::test_AC_02_3_end_after_closing`.
- [ ] TC-02-12 (AC-02.11, D-02.6; Integration): when `desiredStart` `…T09:10:00+07:00`; `…T09:00:00`; `…T09:00:30+07:00`; then 400 `VALIDATION_ERROR` on `desiredStart` for all three. Test: `…::test_AC_02_11_offset_and_step_required`.
- [ ] TC-02-13 (AC-02.13; Integration): given Closed on Sunday; when Book Sunday 09:00; then 422 `OUTSIDE_OPENING_HOURS`, `opensAt null`. Test: `…::test_AC_02_13_closed_day`.
- [ ] TC-02-14 (INV-02.4, R-02.2; Integration): given Monday opens 08:00; when `desiredStart 2026-11-02T01:00:00Z` (= 08:00 Monday local) and `2026-11-01T23:30:00Z` (= 06:30 Monday local; the UTC date is Sunday); then 201; 422 `OUTSIDE_OPENING_HOURS`. Test: `…::test_INV_02_4_local_day_from_dealership_zone`.
- [ ] TC-02-15 (AC-02.12; Integration): given Clock 2026-11-02; when Book 2027-02-01 (91 days) and 2027-01-31 (90 days); then 422 `START_TOO_FAR_AHEAD` (`maxDate 2027-01-31`); 201. Test: `…::test_AC_02_12_ninety_day_horizon`.
- [ ] TC-02-16 (BR-02.1, BR-02.2 (boundaries); Integration): given Clock 09:00:00; when Book exactly 09:00; book OIL at 16:00; then 422 `START_IN_PAST`; 201 (`end` = 17:00, exactly closing). Test: `…::test_boundaries_now_and_closing_time`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
