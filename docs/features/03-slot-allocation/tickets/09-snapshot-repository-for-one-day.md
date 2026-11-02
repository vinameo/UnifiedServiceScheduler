# 09: Snapshot repository for one day

**What to build:** `AllocationSnapshotRepository::forDay()` loads one day's `ResourceSnapshot` in a fixed number of queries, reading busy intervals through the `BusyIntervalReader` seam (`NoBusyIntervals` until F04) for the local day's UTC window (AC-03.13, INV-03.5).

**Blocked by:** [08-openinghours](08-openinghours.md)

**Status:** ready-for-agent

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [DB](../3-database-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-19, TC-03-29

- [ ] TC-03-19 (AC-03.13, INV-03.5; Integration): given `World::saigonD7()`; second run adds 10 technicians; when `forDay(d, '2026-11-02', now)`; count queries with `DB::listen`; then The busy-interval reader is asked for `2026-11-01T17:00Z`–`2026-11-02T17:00Z`; technicians carry `level`; run 1 query count = run 2. Test: `api/tests/Feature/Allocation/AllocationSnapshotRepositoryTest::test_AC_03_13_shifts_in_utc_with_fixed_query_count`.
- [ ] TC-03-29 (INV-03.5; Integration): given `World` with the dealership's `timezone` set to `Europe/Berlin`; when `AllocationSnapshotRepository::forDay(d, '2026-10-25', now)` and `forDay(d, '2026-10-24', now)`; then The reader is asked for `2026-10-24T22:00Z`–`2026-10-25T23:00Z` on the 25th and `2026-10-23T22:00Z`–`2026-10-24T22:00Z` on the 24th. Test: `api/tests/Feature/Allocation/AllocationSnapshotRepositoryTest::test_INV_03_5_dst_day_in_the_repository`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
