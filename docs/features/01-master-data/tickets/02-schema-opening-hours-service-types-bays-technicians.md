# 02: Schema: opening hours, service types, bays, technicians

**What to build:** Create `dealership_opening_hours`, `service_types`, `bays` and `technicians` with models and the read repository (DB §B1). The database refuses zero or non-15-minute durations and levels outside 1–7.

**Blocked by:** [01-prefactor-empty-world-fixture-baytype-and-level](01-prefactor-empty-world-fixture-baytype-and-level.md)

**Status:** done

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-10

- [x] TC-01-10 (INV-01.1, INV-01.2; Integration): when Insert: service of 0 minutes, 50 minutes, `required_level` 0 and 8; technician `level` 0 and 8; opening 17:00 closing 08:00; two rows for one weekday; then Each insert fails (`23514` or `23505`). Test: `…/MasterData/MasterDataSchemaTest::test_INV_01_1_and_01_2_checks_reject_bad_rows` (data provider).
- [x] README §8 checks green; no existing test skipped, deleted or weakened
