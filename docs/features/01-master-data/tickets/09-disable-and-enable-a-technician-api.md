# 09: Disable and enable a technician (API)

**What to build:** `PUT …/technicians/{t}/status` lets an admin disable or enable a technician through `TechnicianService::setActive`, taking the technician row `FOR UPDATE` (INV-01.7). Disabling asks the `UpcomingAssignmentsReader` seam, bound to `NoUpcomingAssignments` until F04 binds the real reader (D-01.11). Enabling twice is a no-op (D-01.12).

**Blocked by:** [08-dealership-details-and-opening-hours](08-dealership-details-and-opening-hours.md)

**Status:** done

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-16, TC-01-20

- [x] TC-01-16 (AC-01.7, INV-01.7; Integration): given `World`; Chi has no appointment; when Admin `PUT …/technicians/{Chi}/status` `{active:false}`; then 200 `active:false`; `AllocationSnapshotRepository` snapshot lists Chi inactive and F03 never pairs her. Test: `api/tests/Feature/MasterData/TechnicianStatusTest::test_AC_01_7_disable_without_bookings`.
- [x] TC-01-20 (AC-01.10, INV-01.8, D-01.12, API §B4; Integration): given Admin of D1; D2's technician; advisor; a disabled technician; when Each row of API §B4 for API-01.6; admin enables the disabled one twice; then Status and `code` as in the table; enable: 200 both times, `active:true`. Test: `…TechnicianStatusTest::test_API_01_6_adversarial_input` (data provider).
- [x] README §8 checks green; no existing test skipped, deleted or weakened
