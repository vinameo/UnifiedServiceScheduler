# 03: Parallel bookings and replays through the real endpoint

**What to build:** 20 parallel `POST /appointments` for one slot give one 201 and 19 × 409 (AC-02.21), and resending the same `Idempotency-Key` replays the first answer (AC-04.3).

**Blocked by:** [02-advisor-books-an-appointment](02-advisor-books-an-appointment.md)

**Status:** ready-for-agent

**Branch:** `feature/02-create-booking`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-02-20, TC-02-21

- [ ] TC-02-20 (AC-02.21, AC-04.1; Concurrency): given Only An + K1; 20 customers, 20 vehicles; when 20 processes `POST /api/v1/appointments` at the same moment (different keys); then 1 × 201, 19 × 409; 1 booking; overlap 0. Test: `ParallelCreateBookingTest::test_AC_04_1_through_the_real_endpoint`.
- [ ] TC-02-21 (AC-04.3; Integration): given Booked with K; when Send the identical request with K; then 201 with the same `id`; `Idempotent-Replayed: true`; 1 booking, 1 outbox event. Test: `CreateBookingTest::test_AC_04_3_replay_through_the_real_endpoint`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
