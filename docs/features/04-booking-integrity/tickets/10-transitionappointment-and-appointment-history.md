# 10: TransitionAppointment and appointment history

**What to build:** `TransitionAppointment` changes status through `canMoveTo()`, frees the allocations of a status that releases the slot in the same transaction, and records one `appointment_events` row per change through `AppointmentEventRecorder` (AC-04.14, INV-04.5, INV-04.6, INV-04.11). A stale version answers 412 before 422 (412/422 order).

**Blocked by:** [09-versioned-updates-and-if-match](09-versioned-updates-and-if-match.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-14, TC-04-26, TC-04-33

- [x] TC-04-14 (AC-04.14, INV-04.5, INV-04.6; Integration): given One appointment per path: CONFIRMED→CANCELLED; HELD→EXPIRED (with a vehicle, and without one); HELD→CANCELLED without a vehicle; CONFIRMED→NO_SHOW; CONFIRMED→CHECKED_IN→IN_PROGRESS→COMPLETED; when `TransitionAppointment` along the path; then At the final status: 0 active allocations, `deactivated_at` set; 1 history row per step; M-04.2 returns 0 rows. Test: `TransitionTest::test_AC_04_14_freeing_statuses_release_allocations` (data provider).
- [x] TC-04-26 (412/422 order; Integration): given Appointment v3 `CONFIRMED`; someone else moved it to `CANCELLED` (v4); when `transition` to `CHECKED_IN` with `If-Match: "3"`; then 412 (not 422) with `appointment.status = CANCELLED`. Test: `TransitionTest::test_stale_version_beats_invalid_transition`.
- [x] TC-04-33 (INV-04.11; Integration): when Create, `touch`, `transition`; then Each operation adds exactly 1 `appointment_events` row, `version` matches `appointments.version`. Test: `AppointmentEventsTest::test_INV_04_11_one_event_per_write`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
