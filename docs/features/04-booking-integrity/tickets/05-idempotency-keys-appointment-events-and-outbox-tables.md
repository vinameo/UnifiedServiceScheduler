# 05: Idempotency keys, appointment events and outbox tables

**What to build:** Create `idempotency_keys`, `appointment_events` and `outbox_messages` with every column F07–F12 need, and the check constraints of DB-04.1–04.5.

**Blocked by:** [04-resource-allocations-with-the-exclude-constraint](04-resource-allocations-with-the-exclude-constraint.md)

**Status:** ready-for-agent

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-30

- [ ] TC-04-30 (DB-04.1–04.5; Integration): when Insert: `period` `(]`; empty `period`; `HELD` without `expires_at`; `CONFIRMED` without vehicle (fails); `EXPIRED` and `CANCELLED` without vehicle (allowed); `version = 0`; two active allocations of one type for one appointment; then Each fails with `23514` or `23505`. Test: `SchemaChecksTest::test_schema_checks` (data provider).
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
