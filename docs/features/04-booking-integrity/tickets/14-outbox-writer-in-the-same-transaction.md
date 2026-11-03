# 14: Outbox writer in the same transaction

**What to build:** `OutboxWriter::append()` writes one event with a row per channel (SMS, EMAIL, DMS) inside the business transaction and refuses to run outside one; a rolled-back booking leaves no outbox rows and no payload carries phone or email (AC-04.20, D-04.4, INV-04.12).

**Blocked by:** [13-idempotency-key-pruning-and-no-testing-routes-in-production](13-idempotency-key-pruning-and-no-testing-routes-in-production.md)

**Status:** done

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-20, TC-04-25

- [x] TC-04-20 (AC-04.20, D-04.4; Integration): when Create a `CONFIRMED` booking; then one forced to roll back; then Booking 1: 3 outbox rows (SMS, EMAIL, DMS) with one `event_id`, `PENDING`, payload without phone/email; booking 2: 0 rows. Test: `OutboxWriterTest::test_AC_04_20_outbox_rows_commit_with_the_booking`.
- [x] TC-04-25 (INV-04.12; Unit): when `OutboxWriter::append` outside a transaction; then `LogicException`. Test: `api/tests/Unit/Notifications/OutboxWriterGuardTest`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
