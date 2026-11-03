# Feature Review · `feature/01-master-data`

Date: 2026-11-03  
Branch: `feature/01-master-data`  
Spec: `docs/features/01-master-data/`

---

## 1. Spec Completeness

- **All Acceptance Criteria (AC-01.1 to AC-01.10):**
  - AC-01.1 / BR-01.1: 3 service types seeded, each with `durationMinutes > 0`, one `requiredLevel` (1–7), and one `requiredBayType` (`ServiceTypesTest::test_AC_01_1_seeded_service_types_are_returned`).
  - AC-01.2 / INV-01.3: Staff of D1 calling D2 for all 4 routes (`GET /dealerships/{d}`, `/service-types`, `/bays`, `/technicians`) returns 404 `DEALERSHIP_NOT_FOUND` (`DealershipScopeTest::test_AC_01_2_other_dealership_is_404`).
  - AC-01.3 / INV-01.4: Bays and technicians endpoints are restricted to staff (`can:staff`); customers receive 403 `FORBIDDEN` (`BaysAndTechniciansTest::test_AC_01_3_customer_cannot_read_bays_or_technicians`).
  - AC-01.4 / INV-01.4: Inactive service types are hidden from customers; customer queries with `includeInactive=true` are ignored and continue returning only active services (`ServiceTypesTest::test_AC_01_4_inactive_service_hidden_from_customers`, `test_customer_cannot_include_inactive`).
  - AC-01.5: Staff `GET /dealerships` returns only their assigned dealership (`DealershipScopeTest::test_AC_01_5_staff_list_only_their_dealership`); customer returns all active dealerships.
  - AC-01.6: MasterDataPage renders 4 tabs with badge counts 3/4/5, read-only seed banner, Level badges, active/not-taking-bookings badges, grouped weekdays and timezone offset ("Asia/Ho_Chi_Minh (UTC+7)") without holiday box (`MasterDataPage.test.tsx`).
  - AC-01.7 / INV-01.7: Disabling a technician without upcoming bookings updates status to `active=false` with a pessimistic row lock (`lockForUpdate()`) inside a transaction (`TechnicianStatusTest::test_AC_01_7_disable_without_bookings`).
  - AC-01.8 / D-01.11 / TC-01-17, TC-01-18, TC-01-19: Refusal with 409 `TECHNICIAN_HAS_BOOKINGS` and race conditions deferred to F04 ticket 08 where appointment bookings and allocations exist; integration seam verified via `NoUpcomingAssignments` and mock tests (`TechnicianStatusTest`).
  - AC-01.10 / INV-01.8 / D-01.12: Enabling an already-active technician or enabling twice is an idempotent no-op returning 200 (`TechnicianStatusTest::test_API_01_6_adversarial_input`).
- **All Invariants (INV-01.1 to INV-01.8):**
  - INV-01.1 & INV-01.2: PostgreSQL check constraints reject service durations of 0 or non-multiple of 15m, required levels outside 1–7, technician levels outside 1–7, opening hours where opens_at >= closes_at, and duplicate weekdays per dealership (`MasterDataSchemaTest`).
  - INV-01.3: Missing, malformed, or foreign dealership UUIDs produce identical RFC 9457 problem+json responses with code `DEALERSHIP_NOT_FOUND` (`DealershipScopeTest::test_INV_01_3_missing_malformed_and_foreign_look_the_same`).
  - INV-01.4: Inactive master data (bays, technicians, service types) is strictly filtered for customer views.
  - INV-01.5: Unique normalized plate key (`plate_key`) and partial unique index on VIN (`WHERE vin IS NOT NULL`) prevent duplicates while allowing null VINs (`VehicleSchemaTest`).
  - INV-01.6: Seeders (`MasterDataSeeder`, `VehicleSeeder`) use deterministic natural keys and stable UUIDs; rerunning them preserves row counts, IDs, and existing disabled technician statuses (`MasterDataSeederTest`).
  - INV-01.7: Status updates take technician row `FOR UPDATE` in `DB::transaction()`.
  - INV-01.8: Missing, malformed, or foreign technician UUIDs return identical 404 `TECHNICIAN_NOT_FOUND` responses.
- **All 11 Tickets:** Marked `done` with complete `.design.md` design notes tables.
- **Manual Acceptance Scenarios (M1 to M6):** Verified on live environment and results recorded in Table A2 of `6-test-case.md`.
- **BA Metrics (M-01.1, M-01.2, M-01.3):** Verified on PostgreSQL: M-01.1 = 0 rows, M-01.2 = 0 rows, M-01.3 = N/A until F08.

---

## 2. SOLID Architecture

- **S (Single Responsibility):**
  - Controllers (`DealershipController`, `ServiceTypeController`, `BayController`, `TechnicianController`) remain thin, validating requests via FormRequests, calling domain services, and returning API Resources.
  - `MasterDataService` encapsulates dealership, service type, bay, and technician querying logic.
  - `TechnicianService` encapsulates status mutation business rules, lock acquisition, and upcoming assignment seam checks.
  - `MasterDataRepository` encapsulates Eloquent queries and database operations.
- **O (Open/Closed):**
  - `BayType` backed enum encapsulates bay types and human-readable labels.
  - `Level` value object encapsulates grade comparison logic (`qualifiesFor()`).
  - `UpcomingAssignmentsReader` seam interface allows F04 to plug in real appointment lookups without modifying `TechnicianService`.
- **L (Liskov Substitution):**
  - Implementations (`MasterDataRepository`, `NoUpcomingAssignments`) strictly conform to their respective interfaces.
- **I (Interface Segregation):**
  - Interfaces (`MasterDataServiceInterface`, `TechnicianServiceInterface`, `MasterDataRepositoryInterface`, `UpcomingAssignmentsReader`) declare only the methods required by callers.
- **D (Dependency Inversion):**
  - Controllers and services depend exclusively on domain interfaces bound in `DomainServiceProvider`.
  - Route model binding resolves `DealershipAccess` via `app(DealershipAccess::class)`.

---

## 3. Security Review (OWASP Top 10)

- **A01 Broken Access Control:**
  - Route middleware `auth:sanctum` protects all master data routes.
  - Gates `can:staff` and `can:admin` restrict sensitive endpoints (bays, technicians, status updates).
  - Staff queries are strictly scoped to their assigned `dealership_id` via `{dealership}` route binding.
  - Access to other dealerships or technicians yields 404 `DEALERSHIP_NOT_FOUND` / `TECHNICIAN_NOT_FOUND` to prevent resource enumeration.
- **A02 Cryptographic Failures:**
  - No secrets, tokens, or credentials handled or leaked in responses or logs.
- **A03 Injection:**
  - Strict FormRequest validation (`UpdateTechnicianStatusRequest`).
  - UUID validation (`Str::isUuid`) before SQL queries.
  - Parameterized queries throughout Eloquent and PostgreSQL check constraints.
- **A04 Insecure Design:**
  - Pessimistic locking (`FOR UPDATE`) inside `DB::transaction()` for technician status mutations prevents concurrent race conditions.
- **A05 Security Misconfiguration:**
  - Standard RFC 9457 `application/problem+json` error responses with stable codes. Internal details and stack traces suppressed.
- **A06 Vulnerable Components:**
  - No new external packages added.
- **A07 Identification and Authentication Failures:**
  - Valid session required for all master data endpoints.
- **A08 Software and Data Integrity Failures:**
  - PostgreSQL constraints (CHECK, UNIQUE, FK) enforce domain rules at the schema level.
  - Reversible migrations with clean rollback (`down`) implementations.
- **A09 Logging and Monitoring Failures:**
  - Responses include `X-Correlation-Id`.
- **A10 SSRF:**
  - No user-controlled outbound HTTP requests.

---

## 4. Findings and Resolutions

- **Finding 1:** `DealershipAccess` was instantiated with `new DealershipAccess` in `AppServiceProvider`.
  - *Fix:* Changed to container resolution `app(DealershipAccess::class)` for proper DI and testing flexibility (`fcf400b`).
- **Finding 2:** Redundant exception alias files `TechnicianHasBookingsException.php` and `TechnicianNotFoundException.php` existed alongside canonical classes `TechnicianHasBookings.php` and `TechnicianNotFound.php`.
  - *Fix:* Removed the duplicate alias stubs (`185b8f9`).
- **Finding 3:** Web tests needed timezone offset assertion robust against locale/environment variations.
  - *Fix:* Formatted timezone with dynamic offset helper `formatTimezoneOffset()` and flexible RegExp assertions in `MasterDataPage.test.tsx`.

---

## 5. Verification Commands (README §8)

- `docker compose exec php php artisan test`: **PASS** (139 tests passed, 882 assertions)
- `docker compose exec php ./vendor/bin/pint --test`: **PASS** (134 files checked, 0 style violations)
- `docker compose exec php ./vendor/bin/phpstan analyse`: **PASS** (121 files analysed, 0 errors)
- `cd web && npm run lint`: **PASS** (ESLint + TypeScript strict clean)
- `cd web && npm run test`: **PASS** (16 test files passed, 64 tests passed)
