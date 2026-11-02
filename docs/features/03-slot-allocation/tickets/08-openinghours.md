# 08: OpeningHours

**What to build:** `OpeningHours` gives the UTC opening window of a local day in the dealership's time zone, tells whether a `TimeRange` fits in it (AC-03.14), and gives `latestStartFor` a duration, including across a DST change (INV-03.5).

**Blocked by:** [07-pluggable-allocationstrategy](07-pluggable-allocationstrategy.md)

**Status:** ready-for-agent

**Branch:** `feature/03-slot-allocation`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [TC](../6-test-case.md) rows below.

**Test cases:** TC-03-20, TC-03-25, TC-03-26

- [ ] TC-03-20 (AC-03.14; Unit): given Seed opening hours; when `fits` 16:00–17:00 Mon; 16:30–17:30 Mon; 09:00–10:00 Sun; 23:30–00:30; then true; false; false; false. Test: `OpeningHoursTest::test_AC_03_14_range_must_fit_one_day_of_opening_hours`.
- [ ] TC-03-25 (Used by F02; Unit): given Seed opening hours; when `latestStartFor('2026-11-02', 120)`; `('2026-11-07', 60)`; `('2026-11-08', 60)`; then 15:00; 11:00; null. Test: `OpeningHoursTest::test_latest_start`.
- [ ] TC-03-26 (INV-03.5; Unit): given Dealership `Europe/Berlin`, opening hours 08:00–17:00, date 2026-10-25 (DST change); when `OpeningHours::windowFor('2026-10-25')`; `SnapshotBuilder` day window; then Opening window UTC 07:00–16:00; day window `2026-10-24T22:00Z`–`2026-10-25T23:00Z` (25 hours). Test: `DayWindowTest::test_INV_03_5_dst_day_uses_zone_rules` (proves the fixture agrees with the zone rules; TC-03-29 proves the real repository).
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
