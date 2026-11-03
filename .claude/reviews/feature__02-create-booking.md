# Feature Review · `feature/02-create-booking`

Date: 2026-11-03  
Branch: `feature/02-create-booking`  
Spec: `docs/features/02-create-booking/`

---

## 1. Spec Completeness

- **All Acceptance Criteria (AC-02.1 to AC-02.21):**
  - AC-02.1: Advisor books an appointment via `POST /api/v1/appointments` with `Idempotency-Key`, returns 201 with `Location`, `ETag: "1"`, and confirmed appointment details (`CreateBookingTest::test_AC_02_1_advisor_books_and_gets_confirmed_appointment`).
  - AC-02.2: Desired start in the past returns 422 `START_IN_PAST` (`CreateBookingTest::test_AC_02_2_start_in_past`).
  - AC-02.3: Service duration extending past closing time returns 422 `OUTSIDE_OPENING_HOURS` with `closesAt` and `latestStart` (`CreateBookingTest::test_AC_02_3_end_after_closing`).
  - AC-02.4: Advisor booking a vehicle belonging to another customer returns 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER` (`CreateBookingTest::test_AC_02_4_vehicle_of_another_customer`).
  - AC-02.5 / INV-02.1: Customer booking derives customer ID from authenticated session; body `customerId` is strictly ignored (`CreateBookingTest::test_AC_02_5_customer_id_in_body_is_ignored`).
  - AC-02.6: Non-existent `serviceTypeId` returns 404 `SERVICE_TYPE_NOT_FOUND` (`CreateBookingTest::test_AC_02_6_unknown_service_type`).
  - AC-02.7: Booking an already booked vehicle returns 409 `VEHICLE_ALREADY_BOOKED` with `existingAppointment` reference details (`CreateBookingTest::test_AC_02_7_vehicle_already_booked`).
  - AC-02.8: Missing `Idempotency-Key` header returns 400 `IDEMPOTENCY_KEY_REQUIRED` (`CreateBookingTest::test_AC_02_8_missing_idempotency_key`).
  - AC-02.9 / INV-02.10: Customer registers vehicles via `POST /me/vehicles`; plate key is normalized (`51g 99999` matches `51G-999.99`) and duplicate returns 409 `PLATE_ALREADY_REGISTERED` (`MyVehiclesTest::test_AC_02_9_plate_is_unique_across_spellings`).
  - AC-02.10 / D-02.5: Customer attempting to book a vehicle owned by another customer receives 404 `VEHICLE_NOT_FOUND` (never 422), preventing vehicle enumeration (`CreateBookingTest::test_AC_02_10_customer_cannot_use_foreign_vehicle`).
  - AC-02.11 / D-02.6: Desired start requires ISO-8601 with timezone offset and strictly adheres to 15-minute grid step (`CreateBookingTest::test_AC_02_11_offset_and_step_required`).
  - AC-02.12: Booking beyond 90-day horizon returns 422 `START_TOO_FAR_AHEAD` with `maxDate` (`CreateBookingTest::test_AC_02_12_ninety_day_horizon`).
  - AC-02.13: Booking on closed days (e.g. Sunday) returns 422 `OUTSIDE_OPENING_HOURS` with `opensAt: null` (`CreateBookingTest::test_AC_02_13_closed_day`).
  - AC-02.14: FOREMAN and ADMIN roles cannot book appointments via `POST /appointments`, receiving 403 `FORBIDDEN` (`CreateBookingTest::test_AC_02_14_foreman_and_admin_cannot_book`).
  - AC-02.15: Multi-field customer search (`?query=`) allows advisors to search by name prefix, phone, or email prefix; query < 3 characters returns 400; rate-limited at 60/min (`CustomerSearchTest::test_AC_02_15_search_rules`).
  - AC-02.16 / INV-02.7: Confirmed booking transactionally writes an outbox message (`AppointmentConfirmed` event with EMAIL, SMS, DMS channels) (`CreateBookingTest::test_AC_02_16_confirmed_booking_writes_outbox`).
  - AC-02.17: New booking form renders Customer, Vehicle, Service, and Date/time sections (`NewBookingPage.test.tsx`).
  - AC-02.18: Advisor completes booking flow, sends valid payload with `Idempotency-Key`, and navigates to confirmed screen (`NewBookingPage.test.tsx`).
  - AC-02.19: API error cards map status and RFC 9457 error codes into user-friendly alerts (`NewBookingPage.test.tsx`, `BookingErrorCard.test.tsx`).
  - AC-02.20: Customer completes 3-step booking flow, inline adds vehicle with `POST /me/vehicles`, confirms, and sees confirmation with masked phone (`BookingFlow.test.tsx`).
  - AC-02.21 / AC-04.1: Concurrency test with 20 parallel processes against `POST /appointments` yields exactly 1 booking and 19 409 `NO_AVAILABILITY` without allocation overlap (`ParallelCreateBookingTest::test_AC_04_1_through_the_real_endpoint`).

- **All Invariants (INV-02.1 to INV-02.10):**
  - INV-02.1: Customer ID always taken from authenticated user session, never client payload.
  - INV-02.2: Foreign or non-existent vehicle returns 404 for customer requests.
  - INV-02.3: Inactive service types or service types belonging to another dealership return 404.
  - INV-02.4: Operating hours and day-of-week boundaries computed in dealership local timezone.
  - INV-02.5: 90-day horizon evaluated against dealership local calendar date.
  - INV-02.6: D-02.7 check order verified: format checks (400) -> existence/scoping (404) -> business rules (422) -> concurrency (409).
  - INV-02.7: Outbox notification written in the exact same DB transaction as appointment and allocation rows.
  - INV-02.8: Single update/write method preserves optimistic locking semantics.
  - INV-02.9: Advisor dealership scoped strictly from advisor staff user account.
  - INV-02.10: Vehicle plate key normalization and vehicle limit (max 10) strictly enforced.

- **Web Components & Hooks (WEB-02.1 to WEB-02.8):**
  - WEB-02.1: `formatInZone` formats all dates and intervals in dealership timezone (`time.ts`).
  - WEB-02.2: `localSlotsForDay` generates 15-minute slot intervals based on opening hours (`slots.ts`).
  - WEB-02.3: `maskPhone` formats customer phone number for display (`0903 *** 218`) (`phone.ts`).
  - WEB-02.4: `TimeGrid` renders morning and afternoon slots with selection state (`TimeGrid.tsx`).
  - WEB-02.5: Staff `NewBookingPage` with customer search autocomplete, vehicle selector, and confirmation (`NewBookingPage.tsx`).
  - WEB-02.6: `BookingErrorCard` standardizes presentation of all booking rejection problem details (`BookingErrorCard.tsx`).
  - WEB-02.7: Customer 3-step flow (`ChooseServicePage`, `ChooseTimePage`, `ContactDetailsPage`, `CustomerBookingConfirmedPage`).
  - WEB-02.8: `BookingDraftContext` provides `sessionStorage` draft persistence across page refreshes and step navigation.

- **BA Metrics (§B2):**
  - M-02.1: `POST /appointments` p95 < 300 ms verified in `CreateBookingPerformanceTest` (p95 ~ 60–100 ms with 20 bays, 30 technicians, 200 existing bookings).
  - M-02.2: Booking success rate metric structured for Prometheus integration in F08.
  - M-02.3: Bookings per channel per day verified via `appointments.source` (`STAFF` vs `CUSTOMER`).
  - M-02.4: Bookings not on 15-minute step: 0 violations guaranteed by `BookingTimeRules` and `CreateAppointmentRequest`.

---

## 2. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `BookingTimeRules`: Handles pure business time validation (past start, 90-day horizon, opening hours, 15-minute step) decoupled from HTTP and Eloquent.
  - `BookingService`: Coordinates multi-step booking orchestration, authorization scoping, candidate discovery, and transactional persistence.
  - `CustomerService` / `CustomerRepository`: Dedicated solely to customer queries and trigram-indexed search.
  - `VehicleRepository`: Encapsulates vehicle persistence, plate key normalization, and ownership checks.
  - `BookingDraftContext`: Manages client-side form state and sessionStorage serialization separately from presentation components.
- **Open/Closed Principle (OCP):**
  - Problem details error rendering maps domain exception classes through `ProblemRenderer` registry without modifying core error handling.
  - Candidate allocation strategies adhere to `CandidateAllocationStrategyInterface`.
- **Liskov Substitution Principle (LSP):**
  - Repository and service contracts (`BookingServiceInterface`, `CustomerServiceInterface`, `VehicleRepositoryInterface`) are fully satisfied by their implementations.
- **Interface Segregation Principle (ISP):**
  - Split focused repository interfaces (`CustomerRepositoryInterface`, `VehicleRepositoryInterface`, `ResourceAllocationRepositoryInterface`) rather than bloated god interfaces.
- **Dependency Inversion Principle (DIP):**
  - Controllers depend exclusively on Domain service interfaces; services depend on repository interfaces bound in `DomainServiceProvider`.

---

## 3. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - Customer bookings derive `customer_id` from `$user->customer->id`, making privilege escalation impossible.
  - Foreign vehicles return 404 `VEHICLE_NOT_FOUND` to customers, preventing IDOR and ownership probing.
  - Advisor bookings are strictly bound to the staff member's `dealership_id`.
  - Roles enforced via Laravel Gates (`book-appointment`, `search-customers`) registered in `AppServiceProvider`.
- **A02:2021 — Cryptographic Failures:**
  - Idempotency keys use UUIDv4; canonical request payload hashing uses SHA-256.
- **A03:2021 — Injection:**
  - Parameterized database operations throughout.
  - PostgreSQL `ILIKE` wildcard injection prevented: `%` and `_` characters are explicitly escaped in `CustomerRepository::search`.
  - PostgreSQL GiST `tstzrange` constructs use parameterized bindings.
- **A04:2021 — Insecure Design:**
  - Rate limiting on customer search (60 requests/minute per advisor) prevents directory harvesting.
  - Double submit prevention via client-side `SubmitButton` and server-side `Idempotency-Key` middleware.
- **A07:2021 — Identification and Authentication Failures:**
  - Sanctum cookie session authentication enforced across all mutating and querying endpoints.
- **A08:2021 — Software and Data Integrity Failures:**
  - Concurrency protected by PostgreSQL `EXCLUDE USING gist` constraint on active allocations.
  - Outbox rows written in the same ACID transaction as appointments.

---

## 4. Extensibility against FC- Constraints

- **FC-02.1 (Several services in one booking):**
  - `CreateBookingInput` accepts structured service data; `BookingTimeRules` computes cumulative duration from service definitions.
- **FC-02.2 (Advisors create walk-in customers):**
  - `CustomerSearch` and `CustomerService` decoupled from authentication; customer records can later exist without `user_id` without breaking search or appointment allocation.
- **FC-02.3 (Pricing, deposits):**
  - Pricing attributes omitted cleanly from current models and tables, avoiding speculative schema bloat.
- **FC-02.4 (More UI languages):**
  - Error copy extracted to centralized dictionaries (`errorCopy.ts`); time and number formatting uses standard `Intl` APIs (`formatInZone`).

---

## 5. Design Pass & Cross-Ticket Consistency

- **One decision, one seam:**
  - Time validation centralized in `BookingTimeRules`.
  - Error translation centralized in `BookingErrorCard` and `errorCopy.ts`.
  - API requests use shared `api` axios client with `Idempotency-Key` generation.
- **Read once, decide on that row:**
  - Vehicle ownership and limit verified in-transaction before insert.
  - Slot allocation relies on database exclusion constraint rather than vulnerable pre-select checks.
- **Bounded lists:**
  - Customer search capped at 20 results; `meta.total` reports accurate database count.
  - Vehicles per customer capped at 10.
- **Adversarial inputs:**
  - 14 adversarial test scenarios verified in `CreateBookingAdversarialInputTest`: past dates, invalid step (09:07), missing offsets, foreign service types, foreign vehicles, extra payload fields.
- **Extract before copy:**
  - Reused `todayInZone` and `formatInZone` across staff and customer screens.
  - Extracted `BookingErrorCard` for consistent error presentation between advisor and customer flows.
- **Copy from one place:**
  - User-facing error strings sourced from spec copy definitions in `errorCopy.ts`.

---

## 6. Test Verification & Metrics Summary

- **Backend Tests:** 314 tests passed (2,512 assertions), 0 failures.
  - Includes 20 parallel processes concurrency test (`ParallelCreateBookingTest`).
  - Includes 50 sequential requests performance test (`CreateBookingPerformanceTest`, p95 < 300 ms).
  - Includes 14-row adversarial dataset test (`CreateBookingAdversarialInputTest`).
- **Frontend Tests:** 27 test files passed (102 tests), 0 failures.
  - Includes full 3-step customer flow test (`BookingFlow.test.tsx`).
  - Includes multi-dealership selection test (`ChooseServicePage.test.tsx`).
  - Includes staff new booking test (`NewBookingPage.test.tsx`).
- **Static Analysis & Linting:**
  - PHP Pint: 265 files passed (0 style issues).
  - PHPStan: 252 files analysed, 0 errors at level 8.
  - ESLint & TypeScript: 0 errors.
