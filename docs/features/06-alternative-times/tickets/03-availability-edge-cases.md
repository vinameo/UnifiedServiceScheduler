# 03: Availability edge cases

**What to build:** Past slots are `reason: PAST`, a closed day answers `closed: true` with no slots, unknown dealership or inactive service is 404, the call runs in a fixed number of queries, and a new booking shows up on the next read (AC-06.4–06.6, AC-06.10, INV-06.3, INV-06.4).

**Blocked by:** [02-free-times-for-one-day-on-new-booking](02-free-times-for-one-day-on-new-booking.md)

**Status:** ready-for-agent

**Branch:** `feature/06-alternative-times`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-06-04, TC-06-05, TC-06-06, TC-06-10, TC-06-18

- [ ] TC-06-04 (AC-06.4; Integration): given Clock 10:05 today; when Today; then Slots ≤ 10:00 `PAST`; 10:15 per F03. Test: `AvailabilityTest::test_AC_06_4_past_slots_are_not_available`.
- [ ] TC-06-05 (AC-06.5; Integration): when Sunday; then 200, `closed: true`, `slots: []`. Test: `…::test_AC_06_5_closed_day`.
- [ ] TC-06-06 (AC-06.6; Integration): given D2 advisor; inactive service; when Advisor asks D1; customer asks the inactive service; then 404 `DEALERSHIP_NOT_FOUND`; 404 `SERVICE_TYPE_NOT_FOUND`. Test: `…::test_AC_06_6_scope`.
- [ ] TC-06-10 (AC-06.10, INV-06.3; Integration): given OIL (36 slots) and a fake 480-minute service (2 slots); when Count queries per call; then Equal. Test: `AvailabilityTest::test_AC_06_10_query_count_independent_of_slots`.
- [ ] TC-06-18 (BR-06.1, INV-06.4; Integration): given Get availability (10:00 free); another user books 10:00; when Get availability again; then 10:00 `false` at once (no cache). Test: `AvailabilityTest::test_list_is_a_hint_and_never_cached`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
