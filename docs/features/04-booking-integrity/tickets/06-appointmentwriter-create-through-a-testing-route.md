# 06: AppointmentWriter::create through a testing route (tracer bullet)

**What to build:** `AppointmentWriter::create()` inserts the appointment and its 3 allocations in one transaction, with an `APT-YYYYMMDD-NNNN` reference in the dealership's local date (D-04.6). Driven through `POST /_testing/appointments`, 20 parallel bookings of one slot give exactly one 201 (AC-04.1) and nothing is left after a failure (AC-04.9).

**Blocked by:** [05-idempotency-keys-appointment-events-and-outbox-tables](05-idempotency-keys-appointment-events-and-outbox-tables.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-01, TC-04-02, TC-04-09, TC-04-34

- [x] TC-04-01 (AC-04.1; Concurrency): given `World` with only An + K1 (OIL); 20 customers, 20 vehicles; when 20 processes `POST /_testing/appointments` 09:00 at the same moment; then Exactly 1 × 201, 19 × 409 `NO_AVAILABILITY`; 1 `appointments` row; overlap query 0. Test: `ParallelBookingTest::test_AC_04_1_only_one_of_twenty_parallel_bookings_wins`.
- [x] TC-04-02 (AC-04.2; Concurrency): given 2 technicians who qualify for OIL, 2 QUICK bays; 10 vehicles; when 10 processes, same time; then Exactly 2 × 201; 2 different technicians, 2 different bays. Test: `…::test_AC_04_2_two_pairs_give_exactly_two_bookings`.
- [x] TC-04-09 (AC-04.9, INV-04.2; Integration): given The allocation repository is wrapped to throw on the `VEHICLE` insert; when `AppointmentWriter::create`; then Throws; 0 appointments; 0 allocations. Test: `…::test_AC_04_9_all_or_nothing`.
- [x] TC-04-34 (D-04.6; Integration): given Dealership at UTC+7; when Create 2 bookings: 2026-11-02 06:30 local (= 01-11 23:30Z) and 2026-11-03; then `reference` matches `^APT-20261102-\d{4,}$` and `^APT-20261103-\d{4,}$`; they differ. Test: `ReferenceTest::test_D_04_6_reference_uses_local_date`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
- [x] Run `/code-review` on the branch and fix findings before later tickets build on it
