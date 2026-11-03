# 02: Appointment enums and the status rules

**What to build:** Add `AppointmentStatus` with `canMoveTo()` and `holdsSlot()`, plus `ResourceType`, `AppointmentEventType`, `OutboxEventType`, `OutboxChannel` (S-04.2). Allowed transitions live only in `canMoveTo()` (D-04.8).

**Blocked by:** [01-prefactor-overlap-assertion-parallel-runner-testing-routes](01-prefactor-overlap-assertion-parallel-runner-testing-routes.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-15

- [x] TC-04-15 (AC-04.15, D-04.8; Unit): when `canMoveTo` for all 8×8 pairs; then Matches D-04.8; `holdsSlot` matches `CONTEXT.md`. Test: `api/tests/Unit/Booking/AppointmentStatusTest::test_AC_04_15_transition_table`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
