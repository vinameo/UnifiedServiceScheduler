# 03: Choosing the new pair

**What to build:** `keepTechnician` keeps the same technician when free, otherwise another qualified one is taken; a vehicle conflict answers 409 `VEHICLE_ALREADY_BOOKED` with nothing changed; overlapping its own old time is allowed (AC-09.4–09.6, INV-09.3, D-09.6).

**Blocked by:** [02-advisor-reschedules-an-appointment](02-advisor-reschedules-an-appointment.md)

**Status:** ready-for-agent

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-04, TC-09-05, TC-09-06, TC-09-08

- [ ] TC-09-04 (AC-09.4; Integration): given X 09:00 with Dũng; Dũng and An free at 14:00, An ranked first normally; when Reschedule to 14:00, `keepTechnician true`; then 200 with Dũng. Test: `…::test_AC_09_4_keeps_the_same_technician`.
- [ ] TC-09-05 (AC-09.5, D-09.6; Integration): given X with Dũng; Dũng busy at 14:00, An free; when Reschedule to 14:00; then 200 with An; `technician_id` matches the active TECHNICIAN allocation. Test: `…::test_AC_09_5_other_technician_when_preferred_is_busy`.
- [ ] TC-09-06 (AC-09.6; Integration): given X's vehicle has another booking 14:00–15:00; when Reschedule X to 14:00; then 409 `VEHICLE_ALREADY_BOOKED`; X unchanged. Test: `…::test_AC_09_6_vehicle_busy_at_new_time`.
- [ ] TC-09-08 (INV-09.3; Integration): given X 10:00–11:00 on K2; when Reschedule to 09:30 (overlaps own start); then 200, still K2 if free. Test: `…::test_move_earlier_overlapping_own_slot`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
