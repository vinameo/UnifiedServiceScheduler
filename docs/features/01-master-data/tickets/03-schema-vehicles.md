# 03: Schema: vehicles

**What to build:** Create `vehicles` with the normalised plate and the VIN unique only when present (DB-01.5, INV-01.5).

**Blocked by:** [02-schema-opening-hours-service-types-bays-technicians](02-schema-opening-hours-service-types-bays-technicians.md)

**Status:** ready-for-agent

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-08

- [ ] TC-01-08 (INV-01.5, DB-01.5; Integration): given Vehicle `51H-123.45`; when Insert vehicle `51h 12345`; two vehicles with NULL VIN; two with the same VIN; then First: `23505`; second: ok; third: `23505`. Test: `…/MasterData/VehicleSchemaTest::test_INV_01_5_plate_key_and_vin_are_unique`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
