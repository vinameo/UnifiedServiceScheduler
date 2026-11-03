# 07: Conflict mapping and pair retries

**What to build:** A `23P01` on `VEHICLE` answers 409 `VEHICLE_ALREADY_BOOKED` at once; on `TECHNICIAN` or `BAY` the whole transaction reruns with the next pair, up to 3 tries, then 409 `NO_AVAILABILITY` with `reason: SLOT_TAKEN` (AC-04.8, AC-04.10, AC-04.11, D-04.16, D-04.17).

**Blocked by:** [06-appointmentwriter-create-through-a-testing-route](06-appointmentwriter-create-through-a-testing-route.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-08, TC-04-10, TC-04-11, TC-04-29, TC-04-41

- [x] TC-04-08 (AC-04.8; Integration): given Vehicle v1 booked 09:00–10:00 (An, K1); Dũng + K2 free; when Book v1 at 09:30; then 409 `VEHICLE_ALREADY_BOOKED` with `existingAppointment.id`; `AllocationRetried` dispatched 0 times. Test: `AppointmentWriterTest::test_AC_04_8_vehicle_clash_fails_at_once`.
- [x] TC-04-10 (AC-04.10, D-04.16; Integration): given Pairs (An,K1),(Dũng,K1)…; an An allocation 09:00–10:00 inserted by hand after the snapshot; when `create` with the stale pairs; then 201 with Dũng; 1 `AllocationRetried`. Test: `…::test_AC_04_10_stale_pair_retries_next`.
- [x] TC-04-11 (AC-04.11; Integration): given 5 pairs; every technician in them already has an overlapping allocation inserted by hand; when `create`; then 409, `reason: SLOT_TAKEN`; exactly 3 tries (`AllocationRetried` = 3). Test: `…::test_AC_04_11_three_tries_then_slot_taken`.
- [x] TC-04-29 (D-04.17, R-04.2; Integration): given Cause a real 23P01 for each resource type; when Parser reads the `QueryException`; then Returns `TECHNICIAN`, `BAY`, `VEHICLE` correctly; an unknown string → `TECHNICIAN` + warning log. Test: `ExclusionViolationParserTest`.
- [x] TC-04-41 (D-04.16, F03 D-03.3; Integration): given `World` with 2 active technicians An, Dũng and 5 QUICK bays; An's allocation 09:00–10:00 inserted by hand after the snapshot; when `create` with the 5 stale pairs; then 201 with Dũng; 1 `AllocationRetried`. Test: `AppointmentWriterTest::test_D_04_16_conflict_on_a_technician_moves_to_the_next_one`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
