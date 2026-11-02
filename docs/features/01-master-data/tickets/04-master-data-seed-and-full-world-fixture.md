# 04: Master data seed and full World fixture

**What to build:** `MasterDataSeeder` and `VehicleSeeder` build the standard dealership (opening hours, 3 service types, 4 bays, 5 technicians with levels), safe to rerun with stable ids (INV-01.6). `World` builds the same dealership for tests and returns the ids.

**Blocked by:** [03-schema-vehicles](03-schema-vehicles.md)

**Status:** ready-for-agent

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-09

- [ ] TC-01-09 (INV-01.6; Integration): given Empty DB; when `db:seed` twice; record ids after run 1; then Row counts unchanged; ids unchanged; a technician disabled between the runs stays disabled. Test: `…/MasterData/MasterDataSeederTest::test_INV_01_6_seeding_twice_changes_nothing`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
