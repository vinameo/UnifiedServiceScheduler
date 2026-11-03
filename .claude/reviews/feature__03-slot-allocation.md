# Feature Review · `feature/03-slot-allocation`

Date: 2026-11-03  
Branch: `feature/03-slot-allocation`  
Spec: `docs/features/03-slot-allocation/`

---

## 1. Spec Completeness

- **All Acceptance Criteria (AC-03.1 to AC-03.14):**
  - AC-03.1 / BR-03.1: Back-to-back intervals do not overlap (`CandidateFinderTest::test_AC_03_1_back_to_back_is_not_an_overlap`).
  - AC-03.2: Partial overlap correctly removes busy technician (`CandidateFinderTest::test_AC_03_2_partial_overlap_removes_technician`).
  - AC-03.3: Range inside busy interval removes technician (`CandidateFinderTest::test_AC_03_3_range_inside_busy_removes_technician`).
  - AC-03.4: All qualified technicians busy returns `NO_TECHNICIAN_AVAILABLE` (`CandidateFinderTest::test_AC_03_4_all_qualified_busy_gives_no_technician_available`).
  - AC-03.5: All bays busy returns `NO_BAY_AVAILABLE` (`CandidateFinderTest::test_AC_03_5_all_bays_busy_gives_no_bay_available`).
  - AC-03.6 / D-03.10: Technician qualification requires `level >= requiredLevel` (`CandidateFinderTest::test_D_03_10_level_at_or_above_qualifies`).
  - AC-03.7: Lowest level ranks first per ADR-03.2 (`LowestLevelFirstStrategyTest::test_AC_03_7_lowest_level_ranks_first`).
  - AC-03.8: No high enough level technician returns `NO_QUALIFIED_TECHNICIAN` (`CandidateFinderTest::test_AC_03_8_no_high_enough_level_gives_no_qualified_technician`).
  - AC-03.9: Fewer booked minutes that day breaks ties within the same level (`LowestLevelFirstStrategyTest::test_AC_03_9_fewer_booked_minutes_ranks_first`).
  - AC-03.10: Inactive technician (`active=false`) is disqualified (`CandidateFinderTest::test_AC_03_10_inactive_technician_is_not_qualified`).
  - AC-03.11: Inactive bay (`active=false`) is disqualified (`CandidateFinderTest::test_AC_03_11_inactive_bay_is_not_available`).
  - AC-03.12 / INV-03.6: Reschedule inside own slot keeps technician by ignoring intervals where `appointmentId == excludeAppointmentId` (`CandidateFinderTest::test_AC_03_12_reschedule_inside_own_slot_keeps_technician`).
  - AC-03.13 / INV-03.5: `AllocationSnapshotRepository::forDay` queries busy intervals in UTC window with fixed query count, preserving `level` and `active` (`AllocationSnapshotRepositoryTest::test_AC_03_13_shifts_in_utc_with_fixed_query_count`).
  - AC-03.14: `OpeningHours::fits()` validates that a time range fits within the dealership's opening hours for a local day (`OpeningHoursTest::test_AC_03_14_range_must_fit_one_day_of_opening_hours`).
- **All Invariants (INV-03.1 to INV-03.8):**
  - INV-03.1: Pure PHP domain — `app/Domain/Allocation` imports no `Illuminate\Http`, `Illuminate\Database`, `Illuminate\Support\Facades`, or `App\Models` (`AllocationIsPurePhpTest::test_INV_03_1_allocation_is_pure_php`).
  - INV-03.2: Every range is half-open `[start, end)`, `start < end`, stored in UTC. Overlap if and only if `s1 < e2 && s2 < e1` (`TimeRangeTest::test_INV_03_2_half_open_overlap`).
  - INV-03.3: Same request + same snapshot produces identical result and pairing order; snapshot collections are pre-sorted before ranking (`LowestLevelFirstStrategyTest::test_INV_03_3_same_input_same_order`).
  - INV-03.4: Candidate pairs contain only active, qualified resources for the requested dealership free for the whole range; foreign dealership or date throws `InvalidArgumentException` (`CandidateFinderTest::test_invalid_requests_throw`).
  - INV-03.5: Day window and opening hours convert between local time and UTC using `dealerships.timezone` for that local date via Carbon; DST transitions correctly yield 23h or 25h windows (`DayWindowTest::test_INV_03_5_dst_day_uses_zone_rules`, `AllocationSnapshotRepositoryTest::test_INV_03_5_dst_day_in_the_repository`).
  - INV-03.6: Busy intervals matching `excludeAppointmentId` are ignored for both technicians and bays.
  - INV-03.7: An empty result contains exactly one failure reason; a non-empty result contains candidate pairs and no failure reason (`CandidateFinderTest::test_D_03_5_both_busy_reports_technician_first`).
  - INV-03.8: `AllocationStrategy` is the exclusive seam for ranking logic; `CandidateFinder` delegates ranking to the strategy (`CandidateFinderTest::test_INV_03_8_ranking_comes_from_the_strategy`).
- **All 10 Tickets:** Marked `done` with complete `.design.md` design notes tables.
- **BA Metrics (M-03.1, M-03.2, M-03.3):**
  - M-03.1 (`find()` time benchmark with 30 technicians × 20 bays × 36 slots): Measured at **1.748 ms** median across 5 runs (runs: 1.732 ms, 1.741 ms, 1.748 ms, 1.753 ms, 1.776 ms), well below the **< 50 ms** threshold (`CandidateFinderPerformanceTest`).
  - M-03.2 & M-03.3: Spec documented; tracking starts with F04/F08.

---

## 2. SOLID Architecture

- **S (Single Responsibility):**
  - `CandidateFinder`: Coordinates qualification filtering, busy checking, and candidate pairing.
  - `LowestLevelFirstStrategy`: Encapsulates technician and bay ranking algorithms.
  - `OpeningHours`: Encapsulates opening hours parsing, local-to-UTC window calculation, and slot fitting.
  - `TimeRange`: Encapsulates immutable half-open UTC interval calculations.
  - `AllocationSnapshotRepository`: Coordinates loading master data and busy intervals into an immutable `ResourceSnapshot`.
- **O (Open/Closed):**
  - `AllocationStrategy` interface enables plugging alternative ranking algorithms without modifying `CandidateFinder`.
  - `BusyIntervalReader` interface enables registering new sources of unavailability (F04 `resource_allocations`, future shifts or outages) without modifying snapshot loading.
- **L (Liskov Substitution):**
  - `LowestLevelFirstStrategy` cleanly substitutes `AllocationStrategy`.
  - `NoBusyIntervals` provides a valid null implementation of `BusyIntervalReader` until F04 attaches persistence.
- **I (Interface Segregation):**
  - Fine-grained interfaces: `CandidateFinderInterface` (`find()`), `AllocationStrategy` (`rankTechnicians()`, `rankBays()`), `BusyIntervalReader` (`forDealership()`), `AllocationSnapshotRepositoryInterface` (`forDay()`).
- **D (Dependency Inversion):**
  - `CandidateFinder` depends on `AllocationStrategy` interface.
  - `AllocationSnapshotRepository` depends on `MasterDataRepositoryInterface` and `BusyIntervalReader`.
  - All interfaces bound in `DomainServiceProvider`.

---

## 3. Security Review (OWASP Top 10)

- **A01 Broken Access Control:**
  - `AllocationSnapshotRepository` strictly scopes all resource queries to `dealership_id`.
  - `CandidateFinder` validates dealership and local date invariants, throwing `InvalidArgumentException` on mismatch.
- **A02 Cryptographic Failures:**
  - Pure scheduling domain handles no user credentials, passwords, or personal tokens.
- **A03 Injection:**
  - Parameterized Eloquent queries in `AllocationSnapshotRepository`; pure PHP type-safe domain objects reject unvalidated inputs.
- **A04 Insecure Design:**
  - In-memory candidate search is deterministic and side-effect free.
  - Concurrency safety and double-booking prevention are enforced by PostgreSQL GiST EXCLUDE constraints in F04.
- **A05 Security Misconfiguration:**
  - Pure PHP domain layer has zero framework or HTTP coupling.
- **A06 Vulnerable Components:**
  - No new external packages added.
- **A07 Identification and Authentication:**
  - Domain logic contains no auth logic; endpoints calling F03 (F02, F06, F09) enforce authentication and authorization.
- **A08 Software and Data Integrity:**
  - Value objects are `final readonly` and immutable.
  - Strict type hints and validation in constructors prevent invalid state.
- **A09 Logging and Monitoring Failures:**
  - Detailed domain exceptions without leaking sensitive customer data.
- **A10 SSRF:**
  - Zero external HTTP or network calls in domain and snapshot repository.

---

## 4. Extensibility against Future Constraints (FC-03.1 to FC-03.4)

- **FC-03.1 (Alternative ranking rules):** Pluggable via `AllocationStrategy`.
- **FC-03.2 (Per-day advisory locks):** Seam reserved for F04 `AppointmentWriter` without modifying F03.
- **FC-03.3 (Multiple daily opening intervals / lunch breaks):** Seam accommodated in `OpeningHours`.
- **FC-03.4 (Shifts, leave, bay outages):** Seam accommodated via composite `BusyIntervalReader`.

---

## 5. Findings and Resolutions

- **Finding 1:** Memory benchmark under high load (30 technicians, 20 bays, 200 busy intervals across 36 slots) needed validation against NFR-02 (< 50 ms).
  - *Resolution:* Implemented `CandidateFinderPerformanceTest` (`@group performance`) achieving a 1.748 ms median across 5 runs.
- **Finding 2:** Pure PHP architectural boundary in `app/Domain/Allocation` required automated enforcement.
  - *Resolution:* Verified by `AllocationIsPurePhpTest` ensuring no Laravel framework dependencies leak into the domain layer.

---

## 6. Verification Commands (README §8)

- `docker compose exec php php artisan test`: **PASS** (204 tests passed, 1205 assertions)
- `docker compose exec php ./vendor/bin/pint --test`: **PASS** (162 files checked, 0 style violations)
- `docker compose exec php ./vendor/bin/phpstan analyse`: **PASS** (148 files analysed, 0 errors)
- `cd web && npm run lint`: **PASS** (ESLint + TypeScript strict clean)
- `cd web && npm run test`: **PASS** (16 test files passed, 64 tests passed)
