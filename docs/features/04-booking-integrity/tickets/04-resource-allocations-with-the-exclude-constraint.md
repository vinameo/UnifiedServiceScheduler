# 04: Resource allocations with the EXCLUDE constraint

**What to build:** Create `resource_allocations` with `period tstzrange`, `btree_gist` and the `resource_allocations_no_overlap` EXCLUDE constraint `WHERE (active)` (CLAUDE.md core rule 1, INV-04.1). `ResourceAllocationRepository` implements `BusyIntervalReader` so F03 snapshots see real busy time (AC-04.21, INV-04.13).

**Blocked by:** [03-appointments-table-reference-sequence-and-version-trigger](03-appointments-table-reference-sequence-and-version-trigger.md)

**Status:** ready-for-agent

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-27, TC-04-31

- [ ] TC-04-27 (AC-04.21, D-03.7, INV-04.13; Integration): given A `HELD` appointment with `expires_at = now - 1s` holds An 09–10; app clock frozen; when `AllocationSnapshotRepository::forDay`; then An not busy 09–10; a still-valid `HELD` is busy. Test: `BusyIntervalReaderTest::test_AC_04_21_expired_holds_are_not_busy`.
- [ ] TC-04-31 (INV-04.1; Integration): given Active BAY allocation K1 09:00–10:00; when Insert by hand K1 09:59–10:30 active; K1 10:00–11:00 active; K1 09:30 inactive; then 23P01; ok; ok. Test: `ExclusionConstraintTest::test_INV_04_1_db_rejects_active_overlap_only`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
