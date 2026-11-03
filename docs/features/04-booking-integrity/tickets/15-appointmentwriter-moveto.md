# 15: AppointmentWriter::moveTo

**What to build:** `AppointmentWriter::moveTo()` changes an appointment's time in the CLAUDE.md order: version update, old allocations inactive, new allocations, outbox, commit; a retry after `23P01` reruns with the same expected version (INV-04.7).

**Blocked by:** [14-outbox-writer-in-the-same-transaction](14-outbox-writer-in-the-same-transaction.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-32

- [x] TC-04-32 (INV-04.7; Integration): given Appointment An+K2 10:00–11:00 v1; when `moveTo` 10:30–11:30 with pair (An,K2); a second time with the first pair taken and `expected` = 1; then First: 200, v2, no self-clash; second: tries the next pair with the same `expected` (no false 412). Test: `AppointmentWriterTest::test_INV_04_7_move_frees_old_before_taking_new`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
