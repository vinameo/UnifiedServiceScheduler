# F03 · Free slots and resource allocation · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- Almost every F03 rule is pure logic → **unit tests** on an in-memory snapshot (`SnapshotBuilder`). Fast, no DB, hundreds of cases.
- The DB-reading part (day window in UTC, query count) → integration tests on real PostgreSQL.
- No screen, no manual scenarios. QA sees F03 through booking results in F02 and the time grid in F06.

## A2. Manual scenarios

None (no screen). Checked indirectly by the F02 manual scenarios.

## A3. Test data

`SnapshotBuilder` (unit) and `World::saigonD7()` (integration).

## A4. Not tested

Performance on a real DB (F06 measures the endpoint p95). DB writes (F04).

## A5. Exit criteria

Every TC green. TC-03-21 (performance) meets its threshold on the CI machine.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-03.1 | TC-03-01 |
| AC-03.2 | TC-03-02 |
| AC-03.3 | TC-03-03 |
| AC-03.4 | TC-03-04 |
| AC-03.5 | TC-03-05 |
| AC-03.6 | TC-03-06 |
| AC-03.7 | TC-03-07 |
| AC-03.8 | TC-03-08 |
| AC-03.9 | TC-03-09, TC-03-10 |
| AC-03.10 | TC-03-12 |
| AC-03.11 | TC-03-13 |
| AC-03.12 | TC-03-17 |
| AC-03.13 | TC-03-19 |
| AC-03.14 | TC-03-20 |
| INV-03.1 | TC-03-22 |
| INV-03.2, BR-03.1 | TC-03-11 |
| INV-03.3 | TC-03-10 |
| INV-03.4 | TC-03-23 |
| INV-03.5 | TC-03-19, TC-03-26, TC-03-29 |
| INV-03.6 | TC-03-17 |
| INV-03.7, D-03.5 | TC-03-04, TC-03-05, TC-03-08, TC-03-16 |
| INV-03.8, FR-03.5 | TC-03-27 |
| D-03.2, D-03.3 | TC-03-24, TC-03-28 |
| D-03.8 | TC-03-17, TC-03-18 |
| D-03.10 (level rule) | TC-03-06, TC-03-14, TC-03-15 |
| `OpeningHours::latestStartFor` (used by F02) | TC-03-25 |
| API §B3 | TC-03-23 |
| M-03.1 | TC-03-21 |
| BR-03.2 (one person, one bay, the whole time) | TC-03-24 (each pair is one person + one bay for the whole `range`) |
| D-03.7 (expired hold) | TC-04-27 in F04 (the SQL reader); F03 has no hold data |

## B2. Test cases

Unit tests live in `api/tests/Unit/Allocation/`. Times in the table are local UTC+7 on Monday 2026-11-02; `SnapshotBuilder` converts them to UTC.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-03-01 | AC-03.1 | Unit | An (level 2) busy 09:00–10:00; Bay K1 free | Search OIL 10:00–11:00 | First pair has An | `<=` used instead of `<` in overlap | `CandidateFinderTest::test_AC_03_1_back_to_back_is_not_an_overlap` |
| TC-03-02 | AC-03.2 | Unit | An busy 09:00–10:00 | Search 09:30–10:30 | No pair has An | Only `start` compared | `…::test_AC_03_2_partial_overlap_removes_technician` |
| TC-03-03 | AC-03.3 | Unit | An busy 08:00–12:00 | Search 09:00–10:00 | An removed | Only checks whether the ends fall inside | `…::test_AC_03_3_range_inside_busy_removes_technician` |
| TC-03-04 | AC-03.4 | Unit | An (level 2), Dũng (level 3), the only technicians, both busy 09–10; K1 free | Search 09:00–10:00 | `pairs = []`, `failure = NO_TECHNICIAN_AVAILABLE` | Wrong reason order | `…::test_AC_03_4_all_qualified_busy_gives_no_technician_available` |
| TC-03-05 | AC-03.5 | Unit | An free; K1, K2 busy 09–10 | Search OIL 09:00–10:00 | `NO_BAY_AVAILABLE` | Bay busy intervals ignored | `…::test_AC_03_5_all_bays_busy_gives_no_bay_available` |
| TC-03-06 | AC-03.6, D-03.10 | Unit | An (2), Dũng (3), Em (6), all free | Search MAINT_20K (level 3) | Candidates Dũng and Em; An never in a pair | `>` instead of `>=`, or lower levels allowed | `…::test_D_03_10_level_at_or_above_qualifies` |
| TC-03-07 | AC-03.7 | Unit | An (2) and Em (6), both free, 0 booked minutes | Search OIL (level 1) | First pair is An | Ranked by id or highest level first | `LowestLevelFirstStrategyTest::test_AC_03_7_lowest_level_ranks_first` |
| TC-03-08 | AC-03.8 | Unit | An (2), Dũng (3), Chi (4), all free; KEV free | Search EV_BATTERY (level 5) | `NO_QUALIFIED_TECHNICIAN` | "Nobody qualifies" merged with "busy" | `CandidateFinderTest::test_AC_03_8_no_high_enough_level_gives_no_qualified_technician` |
| TC-03-09 | AC-03.9 | Unit | Dũng and Chi both level 3; Dũng booked 120 min that day, Chi 60 | Search OIL 15:00–16:00 | Chi before Dũng | Booked-minutes criterion missing | `LowestLevelFirstStrategyTest::test_AC_03_9_fewer_booked_minutes_ranks_first` |
| TC-03-10 | AC-03.9, INV-03.3 | Unit | Two technicians identical except id | Search twice, second time with the input array reversed | Equal results; smaller id (strcmp) first | Depends on input order | `…::test_INV_03_3_same_input_same_order` |
| TC-03-11 | INV-03.2, BR-03.1 | Unit | Range A = 10:00–11:00 | `overlaps` with: 09:00–10:00, 11:00–12:00, 10:15–10:45, 09:00–12:00, 10:59–11:30, 10:00–11:00 | false, false, true, true, true, true | Closed ranges | `TimeRangeTest::test_INV_03_2_half_open_overlap` (data provider) |
| TC-03-12 | AC-03.10 | Unit | Em (level 6, the only one ≥ 5) `active=false`; KEV free | Search EV_BATTERY | `NO_QUALIFIED_TECHNICIAN` | `active` not filtered | `CandidateFinderTest::test_AC_03_10_inactive_technician_is_not_qualified` |
| TC-03-13 | AC-03.11 | Unit | KEV `active=false`; Em free | Search EV_BATTERY | `NO_BAY_AVAILABLE` | Bay `active` not filtered | `…::test_AC_03_11_inactive_bay_is_not_available` |
| TC-03-14 | D-03.10 | Unit | Only Chi (level 4), free | Search a level-4 service | Chi is a candidate | Strict `>` comparison | `…::test_D_03_10_equal_level_qualifies` |
| TC-03-15 | D-03.10, D-03.5 | Unit | An (2) free; Em (6) busy | Search MAINT_20K (level 3) | `NO_TECHNICIAN_AVAILABLE` (Em qualifies but is busy; An does not qualify) | Lower-level technician used, or reason `NO_QUALIFIED_TECHNICIAN` | `…::test_D_03_10_free_but_too_junior_is_not_used` |
| TC-03-16 | D-03.5 | Unit | Every technician and bay busy | Search | `NO_TECHNICIAN_AVAILABLE` | Bays checked first | `…::test_D_03_5_both_busy_reports_technician_first` |
| TC-03-17 | AC-03.12, INV-03.6, D-03.8 | Unit | Appointment X: An + K2 10:00–11:00; Dũng free with fewer minutes than An | Search 10:30–11:30, `exclude = X`, `preferTechnicianId = An`, `preferBayId = K2` | First pair (An, K2) | Own appointment not ignored | `…::test_AC_03_12_reschedule_inside_own_slot_keeps_technician` |
| TC-03-18 | D-03.8 | Unit | `preferTechnicianId` = Phát (inactive) | Search | Same result as without `prefer`, no error | Unqualified preference moved first | `…::test_D_03_8_unqualified_preference_is_ignored` |
| TC-03-19 | AC-03.13, INV-03.5 | Integration | `World::saigonD7()`; second run adds 10 technicians | `forDay(d, '2026-11-02', now)`; count queries with `DB::listen` | The busy-interval reader is asked for `2026-11-01T17:00Z`–`2026-11-02T17:00Z`; technicians carry `level`; run 1 query count = run 2 | Server time zone used; N+1 | `api/tests/Feature/Allocation/AllocationSnapshotRepositoryTest::test_AC_03_13_shifts_in_utc_with_fixed_query_count` |
| TC-03-20 | AC-03.14 | Unit | Seed opening hours | `fits` 16:00–17:00 Mon; 16:30–17:30 Mon; 09:00–10:00 Sun; 23:30–00:30 | true; false; false; false | Allows running past closing | `OpeningHoursTest::test_AC_03_14_range_must_fit_one_day_of_opening_hours` |
| TC-03-21 | M-03.1 | Unit (`@group performance`) | Snapshot of 30 technicians, 20 bays, 200 busy intervals | 36 `find()` calls (08:00…16:45) | Total < 50 ms (5 runs, median) | Complexity blows up | `CandidateFinderPerformanceTest` |
| TC-03-22 | INV-03.1 | Unit | — | Scan `app/Domain/Allocation/**/*.php` | No `use Illuminate\Http`, `Illuminate\Database`, `Illuminate\Support\Facades`, `App\Models` | Domain depends on the framework | `Unit/Architecture/AllocationIsPurePhpTest` |
| TC-03-23 | INV-03.4, API §B3 | Unit | — | `TimeRange(10:00, 10:00)`; `maxPairs: 0`; snapshot of another dealership; snapshot of another day | Each throws `InvalidArgumentException` | Pairs of another dealership returned | `CandidateFinderTest::test_invalid_requests_throw` (data provider) |
| TC-03-24 | D-03.2, D-03.3, BR-03.2 | Unit | 3 technicians T1<T2<T3, 2 bays K1<K2, all free | Search | Exactly 5 pairs: (T1,K1),(T1,K2),(T2,K1),(T2,K2),(T3,K1) | Wrong pairing order | `…::test_D_03_3_pairs_order_and_limit` |
| TC-03-25 | Used by F02 | Unit | Seed opening hours | `latestStartFor('2026-11-02', 120)`; `('2026-11-07', 60)`; `('2026-11-08', 60)` | 15:00; 11:00; null | Wrong boundary | `OpeningHoursTest::test_latest_start` |
| TC-03-26 | INV-03.5 | Unit | Dealership `Europe/Berlin`, opening hours 08:00–17:00, date 2026-10-25 (DST change) | `OpeningHours::windowFor('2026-10-25')`; `SnapshotBuilder` day window | Opening window UTC 07:00–16:00; day window `2026-10-24T22:00Z`–`2026-10-25T23:00Z` (25 hours) | A fixed offset added | `DayWindowTest::test_INV_03_5_dst_day_uses_zone_rules` (proves the fixture agrees with the zone rules; TC-03-29 proves the real repository) |
| TC-03-27 | INV-03.8, FR-03.5 | Unit | A fake strategy that reverses the order | `CandidateFinder` with that strategy | Pair order is reversed too | Finder ranks by itself | `CandidateFinderTest::test_INV_03_8_ranking_comes_from_the_strategy` |
| TC-03-28 | D-03.3 | Unit | 2 technicians T1<T2 (both qualify for OIL), 5 QUICK bays B1<…<B5, all free | Search | Pairs (T1,B1),(T1,B2),(T2,B1),(T1,B3),(T2,B2); both technicians in the first 5 | Technician-major order | `CandidateFinderTest::test_D_03_3_first_pairs_spread_over_technicians` |
| TC-03-29 | INV-03.5 | Integration | `World` with the dealership's `timezone` set to `Europe/Berlin` | `AllocationSnapshotRepository::forDay(d, '2026-10-25', now)` and `forDay(d, '2026-10-24', now)` | The reader is asked for `2026-10-24T22:00Z`–`2026-10-25T23:00Z` on the 25th and `2026-10-23T22:00Z`–`2026-10-24T22:00Z` on the 24th | Fixed offset in the real repository | `api/tests/Feature/Allocation/AllocationSnapshotRepositoryTest::test_INV_03_5_dst_day_in_the_repository` |

## B3. Fixtures

`Tests\Support\Allocation\SnapshotBuilder` (_illustrative_):

```php
$snapshot = SnapshotBuilder::day('2026-11-02', 'Asia/Ho_Chi_Minh')
    ->technician('An', level: 2)
    ->busy('An', '09:00-10:00', appointment: 'X')
    ->bay('K1', 'QUICK')
    ->build();
$request = SnapshotBuilder::request('OIL_CHANGE', '10:00-11:00');
```

Technician and bay ids are fixed UUIDs derived from the name (`Uuid::uuid5`), so id-order tests are readable.

## B4. Regression

F00 and F01 tests. F03 does not change their code.
