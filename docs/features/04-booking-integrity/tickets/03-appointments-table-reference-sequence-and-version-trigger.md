# 03: Appointments table, reference sequence and version trigger

**What to build:** Create `appointments` with `version` starting at 1, the `appointment_reference_seq` sequence and the trigger that refuses any update that does not raise `version` by 1 (D-04.7, INV-04.3).

**Blocked by:** [02-appointment-enums-and-the-status-rules](02-appointment-enums-and-the-status-rules.md)

**Status:** ready-for-agent

**Branch:** `feature/04-booking-integrity`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-04-13

- [ ] TC-04-13 (AC-04.13, D-04.7; Integration): given Appointment at version 1; when `DB::table('appointments')->update(['status' => 'CANCELLED'])` without changing the version; then `version = version + 2`; then Both throw `QueryException` (P0001). Test: `VersionTriggerTest::test_AC_04_13_update_without_increment_is_rejected`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
