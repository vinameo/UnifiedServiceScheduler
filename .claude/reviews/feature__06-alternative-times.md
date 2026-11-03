# Feature Review · `feature/06-alternative-times`

Date: 2026-11-04  
Branch: `feature/06-alternative-times`  
Spec: `docs/features/06-alternative-times/`

---

## 1. Branch Review Overview

- **Diff Overview:** `git diff develop...HEAD` across 57 files (+5,500 / -170 lines).
- **Tickets Executed (01–10):**
  1. `01-prefactor-timegrid-takes-chip-state-from-props`: Decoupled `TimeGrid` from hardcoded times to take per-chip state (`time`, `available`, `reason`) from props while maintaining backwards compatibility and visual presentation.
  2. `02-free-times-for-one-day-on-new-booking`: Tracer bullet delivering `AvailabilityCalculator::forDay`, `AvailabilityController::availability` endpoint (`GET /dealerships/{dealership}/availability`), `AvailabilityResource`, and frontend `SlotGrid` embedded in staff `NewBookingPage`.
  3. `03-availability-edge-cases`: Handled past slots (`reason: PAST`), closed days (`closed: true`, empty slots array), cross-dealership and inactive service scoping (404), constant query count independent of slot count (`INV-06.3`), and no-cache guarantees (`INV-06.4`).
  4. `04-alternativetimesfinder-near`: Implemented `AlternativeTimesFinder::near` with tie-breaking order (same-day distance from wanted time, earlier first on ties, multi-day fallback up to +7 days, 90-day booking horizon limit, and 3-slot cap).
  5. `05-alternatives-on-409-no-availability`: Caught `NoAvailability` in `BookingService::create`, computed suggestions outside the failed transaction on a fresh snapshot (`ADR-06.3`), and attached `alternatives` array to 409 responses, preserved during idempotent replays.
  6. `06-suggestion-chips-in-the-error-card`: Added `AlternativeChips` to `BookingErrorCard`, allowing 1-click suggestion selection on 409 conflict, auto-dismissing error state, updating selected time, and cycling idempotency keys.
  7. `07-next-available-time`: Implemented `AlternativeTimesFinder::next` and `GET /dealerships/{dealership}/availability/next`, returning the earliest free slot within 7 days, constrained by the 90-day booking horizon.
  8. `08-free-slot-counts-and-fully-booked-day-for-customers`: Integrated customer `ChooseTimePage` with slot availability counts, `FullyBookedCard` with next available slot chip, and seamless date/slot transitions.
  9. `09-ignore-an-appointment-s-own-slot`: Added `excludeAppointmentId` parameter with strict visibility validation (`findVisibleTo`), ignoring an appointment's held intervals during candidate checks for reschedule flows, plus adversarial test sweep (`AvailabilityAdversarialInputTest`).
  10. `10-acceptance-and-performance`: Authored high-scale benchmark `AvailabilityPerformanceTest` (20 bays, 30 technicians, 200 bookings, p95 = 41.63 ms < 300 ms), end-to-end acceptance suite `AlternativeTimesAcceptanceTest` (M1–M4), and documented BA metrics.

---

## 2. Spec Completeness

- **All Acceptance Criteria (AC-06.1 to AC-06.16):**
  - **AC-06.1 / INV-06.1:** Only qualified technician busy 09:00–10:00 blocks overlapping slot starts (08:15–09:45) for 60-min service; non-overlapping starts (08:00, 10:00) remain free (`AvailabilityTest::test_AC_06_1_busy_technician_blocks_overlapping_starts`).
  - **AC-06.2 / INV-06.2:** Opening hours 08:00–17:00 with 60-min service produces last start at 16:00 (`AvailabilityTest::test_AC_06_2_last_start_is_close_minus_duration`).
  - **AC-06.3 / D-06.5:** 409 `NO_AVAILABILITY` attaches nearest alternative slots computed after transaction rollback (`AlternativesOnConflictTest::test_AC_06_3_conflict_returns_alternatives`).
  - **AC-06.4 / D-06.2:** At 10:05, slots $\le$ 10:00 return `available: false` with `reason: PAST` evaluated against application now (`AvailabilityTest::test_AC_06_4_past_slots_are_not_available`).
  - **AC-06.5 / D-06.3:** Querying closed day returns 200 OK with `closed: true` and `slots: []` (`AvailabilityTest::test_AC_06_5_closed_day`).
  - **AC-06.6 / INV-06.6:** Foreign dealership returns 404 `DEALERSHIP_NOT_FOUND`; customer requesting inactive service returns 404 `SERVICE_TYPE_NOT_FOUND` (`AvailabilityTest::test_AC_06_6_scope`).
  - **AC-06.7 / D-06.4:** Suggestions prioritize same-day alternatives ordered by distance from wanted time, then chronologically by day for days +1..+7 (`AlternativesOnConflictTest::test_AC_06_7_same_day_first_then_next_days`).
  - **AC-06.8:** When next 8 days are fully booked, `alternatives` returns empty list without searching beyond 7 days (`AlternativesOnConflictTest::test_AC_06_8_no_alternatives_within_a_week`).
  - **AC-06.9:** Ties in distance from wanted time (e.g. 08:00 and 10:00 for wanted 09:00) order earlier slot first (`AlternativeOrderTest::test_AC_06_9_ties_prefer_earlier`).
  - **AC-06.10 / INV-06.3:** Database query count is constant (single snapshot fetch) regardless of whether service has 36 slots or 2 slots (`AvailabilityTest::test_AC_06_10_query_count_independent_of_slots`).
  - **AC-06.11 / INV-06.7:** Excluded appointment allows slot reuse for visible owner, but foreign customer excluding same appointment receives 404 `APPOINTMENT_NOT_FOUND` (`AvailabilityTest::test_AC_06_11_exclude_own_appointment_only`).
  - **AC-06.12 / D-06.7:** Next available endpoint finds earliest free slot within 7 days starting after given timestamp (`NextAvailableTest::test_AC_06_12_next_free_slot`).
  - **AC-06.13 / WEB-06.3:** Web `SlotGrid` displays unavailable slots struck through with `aria-disabled` and accessible tooltip (`SlotGrid.test.tsx`).
  - **AC-06.14 / WEB-06.5:** Customer `ChooseTimePage` displays "This day is fully booked" and "Next available slot" banner when free count is 0; tapping chip jumps to the suggested date and time (`ChooseTimePage.test.tsx`).
  - **AC-06.15 / WEB-06.4:** Staff `NewBookingPage` renders alternative chips on 409 conflict; selecting a chip selects that slot, clears the error, and prepares a new idempotency key (`NewBookingPage.test.tsx`).
  - **AC-06.16 / NFR-02:** High load benchmark (20 bays, 30 technicians, 200 bookings) yields p95 latency of 41.63 ms, well below 300 ms limit (`AvailabilityPerformanceTest::test_TC_06_13_get_availability_p95_under_300ms`).

- **Invariants Enforced (INV-06.1 to INV-06.7):**
  - **INV-06.1:** Slot availability strictly mirrors F03 `CandidateFinder` on the day's resource snapshot.
  - **INV-06.2:** Opening hours define boundaries: first slot at opening time, last start at closing minus duration, stepped in 15-minute increments.
  - **INV-06.3:** Snapshot loaded once per day query; zero queries per individual slot.
  - **INV-06.4:** Zero inter-request caching; availability reflects live database state immediately.
  - **INV-06.5:** Suggestions cap at 3 slots, never duplicate, never match requested time, and never exceed 90 days ahead (`MAX_DAYS_AHEAD`).
  - **INV-06.6:** Scoping enforced via route model binding and service type validation.
  - **INV-06.7:** Exclude appointment parameter requires explicit visibility via `AppointmentRepository::findVisibleTo`.

- **Business Analytics Metrics (§B2):**
  - **M-06.1:** Availability p95 latency measured at 41.63 ms against < 300 ms threshold.
  - **M-06.2 & M-06.3:** Telemetry hooks in place in `BookingService` and `NoAvailability` exception.

---

## 3. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `AvailabilityCalculator`: Dedicated purely to generating slot states for a single dealership date given a snapshot.
  - `AlternativeTimesFinder`: Dedicated purely to finding nearby and next free time ranges across day boundaries.
  - `AvailabilityService`: Coordinates domain services, service type resolution, and security visibility checks.
  - `AvailabilityResource`: Pure transformation layer converting domain value objects into API contract format.
  - `SlotGrid`: Isolated UI component responsible solely for slot presentation, strike-through styling, and selection.
  - `AlternativeChips`: Reusable UI component dedicated to rendering suggestion chips.
- **Open/Closed Principle (OCP):**
  - `AvailabilityCalculatorInterface` and `AvailabilityServiceInterface` allow new allocation strategies or alternate suggestion algorithms without changing consumers.
  - `AllocationRequest` cleanly incorporates `excludeAppointmentId` (and future `preferTechnicianId` for FC-06.1) without modifying the candidate finding pipeline.
- **Liskov Substitution Principle (LSP):**
  - `AvailabilityService` implements `AvailabilityServiceInterface`, adhering completely to method contracts and exception behaviors.
- **Interface Segregation Principle (ISP):**
  - Read-only availability operations segregated into `AvailabilityServiceInterface`, preventing pollution of `BookingServiceInterface` or `MasterDataRepositoryInterface`.
- **Dependency Inversion Principle (DIP):**
  - `AvailabilityController` depends strictly on `AvailabilityServiceInterface` abstractions injected via Laravel container (`DomainServiceProvider`).

---

## 4. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - Route model binding `{dealership}` ensures staff callers cannot access foreign dealerships (404 `DEALERSHIP_NOT_FOUND`).
  - Customer queries require active service types (404 `SERVICE_TYPE_NOT_FOUND`), preventing probing of unreleased or inactive services.
  - `excludeAppointmentId` resolves via `findVisibleTo($actor, $id)`, returning 404 `APPOINTMENT_NOT_FOUND` if the caller has no permission to view the appointment, preventing ID harvesting or appointment presence disclosures.
  - Both endpoints are rate-limited with `throttle:120,1`.
- **A02:2021 — Cryptographic Failures:**
  - All IDs are standard UUIDv4 format, validated strictly before query execution.
- **A03:2021 — Injection:**
  - All SQL queries use parameter bindings. Date and timestamp parameters are validated against strict regex and ISO 8601 parser formats, preventing syntax or type confusion.
- **A04:2021 — Insecure Design:**
  - Zero trust placed in client-side slot state; booking creation still validates and acquires locks under database exclusion constraints.
  - Inter-request caching is forbidden (INV-06.4), preventing race conditions caused by stale cached availability.
- **A05:2021 — Security Misconfiguration:**
  - Standard RFC 9457 Problem Details error responses ensure no internal stack traces or database errors are exposed to callers.
- **A06:2021 — Vulnerable and Outdated Components:**
  - Zero external packages added; all functionality built using existing Laravel and React dependencies.
- **A07:2021 — Identification and Authentication Failures:**
  - All availability endpoints require authenticated Sanctum sessions (`auth:sanctum`).
- **A08:2021 — Software and Data Integrity Failures:**
  - Concurrency safety maintained via PostgreSQL exclusion constraints (`EXCLUDE USING gist`) and transactional rollback handling (`ADR-06.3`).
- **A09:2021 — Security Logging and Monitoring Failures:**
  - Error responses and 409 conflict alternatives log appropriate telemetry attributes for capacity shortage and probing monitoring.
- **A10:2021 — Server-Side Request Forgery (SSRF):**
  - No outbound HTTP requests performed.

---

## 5. Extensibility against Future Constraints

- **FC-06.1 (Suggestions by familiar technician):**
  - `AllocationRequest` already has `preferTechnicianId`; `AlternativeTimesFinder` can pass this preference into `CandidateFinder` when implemented.
- **FC-06.2 (Very high load):**
  - Caching is forbidden today per INV-06.4; snapshot loading is single-query and in-memory calculation takes < 42 ms at scale, meeting SLAs comfortably.
- **FC-06.3 (Suggest other dealerships):**
  - `AlternativeTimesFinder` is parameterized by `dealershipId`, easily extensible to iterate across a list of nearby dealerships.

---

## 6. Verification & Quality Gates

- **Backend Tests:** 431 tests passed (3,824 assertions) on real PostgreSQL container, including:
  - `AvailabilityPerformanceTest`: 30 calls p95 = 41.63 ms (< 300 ms threshold).
  - `AlternativeTimesAcceptanceTest`: M1–M4 manual scenarios fully automated and green.
  - Complete F06 test suite (`AvailabilityTest`, `AlternativesOnConflictTest`, `NextAvailableTest`, `AvailabilityAdversarialInputTest`, `AlternativeOrderTest`).
- **Pint:** Zero styling issues across 305 files.
- **PHPStan:** Level 8 static analysis passing with 0 errors across 292 files.
- **Frontend Vitest:** 40 test files passed (164 unit & component tests).
- **Frontend ESLint & TypeScript:** Passing with 0 errors (`eslint . && tsc -b`).
