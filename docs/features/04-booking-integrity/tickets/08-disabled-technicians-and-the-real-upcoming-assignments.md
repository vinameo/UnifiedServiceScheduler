# 08: Disabled technicians and the real upcoming-assignments reader

**What to build:** Every allocation write takes the technician row `FOR SHARE` and treats an inactive technician like a taken one (AC-04.24, INV-04.15). Bind F01's `UpcomingAssignmentsReader` to the real reader over `resource_allocations`, so disabling a technician with upcoming appointments answers 409 `TECHNICIAN_HAS_BOOKINGS` (AC-04.25, D-04.20).

**Blocked by:** [07-conflict-mapping-and-pair-retries](07-conflict-mapping-and-pair-retries.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-42, TC-01-17, TC-01-18, TC-01-19

> TC-01-17, TC-01-18, TC-01-19 are listed in F01's `6-test-case.md` and written here (F04 `6-test-case.md` B1).

- [x] TC-04-42 (AC-04.24, INV-04.15; Integration): given Pairs (An,K1),(Dũng,K1) from a snapshot; then An set `active = false`; when `create` with those pairs; then 201 with Dũng; 0 allocations for An. Test: `AppointmentWriterTest::test_INV_04_15_disabled_technician_is_never_allocated`.
- [x] TC-01-17 (AC-01.8, D-01.11; Integration (written in F04, when the real reader exists)): given Chi has a `CONFIRMED` booking tomorrow and a `COMPLETED` one yesterday; when Admin disables Chi; then 409 `TECHNICIAN_HAS_BOOKINGS`, `count 1`, `appointments[0].reference` = tomorrow's; Chi still active. Test: `…TechnicianStatusTest::test_AC_01_8_disable_refused_with_upcoming_bookings`.
- [x] TC-01-18 (AC-01.9, INV-01.7, D-01.13; Concurrency (in F04)): given `onlyTechnicians(['Võ Thị Chi'])`; 20 runs; when Process 1 books Chi's only free slot, process 2 disables Chi, released together; then Each run ends either (booking 201, disable 409, Chi active) or (disable 200, booking 409 `NO_AVAILABILITY`); never Chi inactive with an active allocation. Test: `ParallelTechnicianDisableTest::test_AC_01_9_disable_and_booking_never_both_win`.
- [x] TC-01-19 (D-01.11; Integration (in F04)): given Chi's only appointment is a `HELD` with `expires_at` in the past; when Admin disables Chi; then 200; Chi inactive. Test: `…TechnicianStatusTest::test_expired_hold_does_not_block_disable`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
