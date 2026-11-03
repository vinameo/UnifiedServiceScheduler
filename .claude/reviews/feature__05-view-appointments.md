# Feature Review · `feature/05-view-appointments`

Date: 2026-11-03  
Branch: `feature/05-view-appointments`  
Spec: `docs/features/05-view-appointments/`

---

## 1. Branch Review Overview

- **Diff Overview:** `git diff develop...HEAD` across 84 files (+6,838 / -80 lines).
- **Tickets Executed (01–10):**
  1. `01-prefactor`: Centralized visible appointment lookup `findVisibleTo`, explicit `{appointment}` route binding (D-05.9), `AppointmentNotFound` exception (404), and `CursorPaginator` helper.
  2. `02-appointment-details-for-staff`: Staff details API (`GET /appointments/{id}`) returning `AppointmentResource` with `ETag`, `AppointmentQueryService`, staff details page (`/staff/appointments/:id`), masked phone with "Show number", and `appointmentQuery` TanStack cache wrapper.
  3. `03-appointment-history-and-closed-details`: Staff history API (`GET /appointments/{id}/history`), `AppointmentEventResource` with PII sanitization, closed appointment banner, and chronological history timeline.
  4. `04-staff-day-list-api`: Staff day list API (`GET /dealerships/{dealership}/appointments`), `DealershipAppointmentsRequest`, dealership timezone window conversion `[00:00, 24:00)`, eager loading relations without N+1, and cursor pagination with `meta.total`.
  5. `05-appointment-list-screen`: Staff appointment list screen (`/staff/appointments`), URL query filter synchronization, infinite scroll with "Load more", footer with real `meta.total`, and 60-second background polling.
  6. `06-my-bookings-api`: Customer My Bookings API (`GET /me/appointments`), `MyAppointmentsRequest`, scopes `upcoming` and `past`, exclusion of `HELD` and `EXPIRED` bookings.
  7. `07-my-bookings-and-customer-details-screens`: Customer My Bookings page (`/my-bookings`) with segmented tabs (`?tab=past`), booking cards, empty states, and customer details page (`/my-bookings/:id`).
  8. `08-links-between-screens`: Added D-05.8 navigation links: "View appointment" on staff confirmed booking, "View existing booking" on `VEHICLE_ALREADY_BOOKED` card, "View my bookings" on customer confirmed booking, "My bookings" in customer header, and "Check appointment list" in `IdempotentRetryPanel`.
  9. `09-access-and-adversarial-sweep`: Adversarial input suite (`AppointmentListAdversarialInputTest`), IDOR scan on all registered `{appointment}` routes (`ShowAppointmentTest::test_D_05_9_every_appointment_route_is_scoped`), parameter bound validations.
  10. `10-acceptance`: Automated acceptance test suite `ViewAppointmentsAcceptanceTest` simulating M1–M6 scenarios, manual scenario results documented in `6-test-case.md`, BA metrics evaluation, and whole-branch review.

---

## 2. Spec Completeness

- **All Acceptance Criteria (AC-05.1 to AC-05.10, AC-00.7, AC-00.8):**
  - **AC-05.1 / INV-05.2:** Booking survives application restart; subsequent fetch returns identical representation and `ETag: "1"` (`ShowAppointmentTest::test_AC_05_1_appointment_survives_restart`).
  - **AC-05.2 / INV-05.3:** Date filter converts to local dealership timezone window; an early 06:30 appointment (23:30 UTC previous day) correctly appears under local date `2026-11-02` (`DealershipAppointmentsTest::test_AC_05_2_date_is_local_to_the_dealership`).
  - **AC-05.3:** Non-existent appointment returns 404 `APPOINTMENT_NOT_FOUND` (`ShowAppointmentTest::test_AC_05_3_unknown_id`).
  - **AC-05.4 / AC-05.8:** Customer My Bookings upcoming and past partitioning; customer A sees only their bookings, customer B's are excluded; `HELD` and `EXPIRED` hidden (`MyAppointmentsTest::test_AC_05_4_and_05_8_upcoming_and_past`).
  - **AC-05.5 / INV-05.1:** Staff of foreign dealership accessing appointment receives 404 `APPOINTMENT_NOT_FOUND` (`ShowAppointmentTest::test_AC_05_5_staff_of_another_dealership_gets_404`).
  - **AC-05.6 / D-05.2:** Day list filters by technician, bay, and multiple statuses; ordered by `start_at ASC, id ASC` (`DealershipAppointmentsTest::test_AC_05_6_filters`).
  - **AC-05.7 / INV-05.4 / INV-05.5:** Cursor-based pagination across 120 bookings with `limit=50` delivers 50, 50, 20 distinct records without gaps or duplicates, with constant `meta.total = 120` (`DealershipAppointmentsTest::test_AC_05_7_cursor_paging_has_no_gaps_or_duplicates`).
  - **AC-05.9:** Web appointment list reflects URL filter changes, supports infinite scrolling, and displays formatted counter footer (`AppointmentListPage.test.tsx`).
  - **AC-05.10 / D-05.7:** Closed appointment details screen displays banner and cancellation timeline event (`AppointmentDetailsPage.test.tsx`).
  - **AC-00.7:** Authenticated customer calling staff day list receives 403 `FORBIDDEN` (`DealershipAppointmentsTest::test_AC_00_7_customer_cannot_list_staff_appointments`).
  - **AC-00.8:** Authenticated customer accessing another customer's booking receives 404 `APPOINTMENT_NOT_FOUND`, preventing existence disclosures (`ShowAppointmentTest::test_AC_00_8_customer_cannot_see_another_customers_appointment`).

- **Invariants (INV-05.1 to INV-05.7):**
  - **INV-05.1 / D-05.9:** Customer and dealership scoping enforced directly in SQL `WHERE` clauses via `AppointmentRepository::findVisibleTo`, bound to `{appointment}` routes in `AppServiceProvider`.
  - **INV-05.2:** `ETag` header formatted as `"{$version}"` attached to all appointment responses.
  - **INV-05.3:** Date parameter parsed in `dealership.timezone`, accurately computing DST-safe UTC timestamps for `[00:00, 24:00)`.
  - **INV-05.4:** Stable deterministic pagination orders: staff list `(start_at ASC, id ASC)`, customer upcoming `(start_at ASC, id ASC)`, customer past `(start_at DESC, id DESC)`.
  - **INV-05.5:** Page limit validated in `[1, 200]`, defaulting to 50.
  - **INV-05.6:** Foreign technician/bay filters return empty array `200 OK` (`total: 0`), revealing zero cross-dealership data.
  - **INV-05.7:** History endpoints restricted to staff of owning dealership; recursive payload sanitization strips all personal phone and email data (`AppointmentHistoryTest::test_D_05_4_history_is_staff_only`).

- **Business Analytics Metrics (§B2):**
  - **M-05.1 (Day list p95 < 200 ms):** SQL query utilizes composite index `appointments_dealership_start_idx (dealership_id, start_at, id)` with constant query count regardless of row volume (TC-05-12); monitored in telemetry (F08).
  - **M-05.2 (Probing threshold 0 rows):** Monitored query `SELECT user_id, count(*) FROM api_events WHERE code = 'APPOINTMENT_NOT_FOUND' ... HAVING count(*) > 50` yields 0 rows.

---

## 3. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `AppointmentRepository`: Solely handles database querying, relation eager loading, and tenant-scoped `WHERE` clause formulation.
  - `AppointmentQueryService`: Encapsulates business logic, authorization checks via `AppointmentPolicy`, and query orchestration.
  - `CursorPaginator`: Pure helper dedicated to cursor encoding/decoding and keyset pagination math.
  - `AppointmentResponder`: Reusable HTTP response factory ensuring RFC-compliant `ETag` and `Location` headers.
  - `AppointmentEventResource`: Distinct serialization layer for history audit events with recursive PII redaction.
- **Open/Closed Principle (OCP):**
  - Explicit route model binding (`Route::bind('appointment', ...)`) automatically intercepts and protects future routes (F07 cancel, F09 reschedule, F12 check-in) without altering individual controller handlers.
  - Web `AppointmentDetailsLayout` provides slot architecture (`actions`, `banner`) permitting downstream features to inject action buttons cleanly.
- **Liskov Substitution Principle (LSP):**
  - `AppointmentRepositoryInterface` and `AppointmentQueryServiceInterface` implementations fully fulfill their type contracts and can be substituted transparently in testing or alternative storage providers.
- **Interface Segregation Principle (ISP):**
  - Query operations segregated in `AppointmentQueryServiceInterface`, decoupled from mutation concerns in `BookingServiceInterface` and `AppointmentWriterInterface`.
- **Dependency Inversion Principle (DIP):**
  - Controllers depend exclusively on abstract service interfaces (`AppointmentQueryServiceInterface`), bound via Laravel's service container in `DomainServiceProvider`.

---

## 4. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - Complete IDOR mitigation: Every endpoint referencing `{appointment}` resolves via `findVisibleTo(auth()->user(), $id)`.
  - Scoping is enforced in the initial SQL query rather than in-memory `authorize()` checks, returning uniform 404 `APPOINTMENT_NOT_FOUND` to prevent existence oracles.
  - History route strictly protected with `can:staff` middleware.
  - Cross-route audit verified via `ShowAppointmentTest::test_D_05_9_every_appointment_route_is_scoped`.
- **A02:2021 — Cryptographic Failures:**
  - Secure UUIDv4 identifiers used for all appointments and events.
  - Cursors use URL-safe base64 encoding over non-sensitive tuple keys (`start_at|id`).
- **A03:2021 — Injection:**
  - All database queries parameterized using Laravel Eloquent and Query Builder; zero raw SQL concatenation.
  - UUID format validation (`Str::isUuid`) rejects malformed strings before Postgres execution, avoiding SQLSTATE 22P02 exceptions.
- **A04:2021 — Insecure Design:**
  - Optimistic concurrency control anchored by `ETag` and `If-Match` headers.
  - Timezone calculations executed server-side based on dealership configuration, immune to client-side clock tampering.
- **A05:2021 — Security Misconfiguration:**
  - RFC 9457 Problem Details responses standardize error structure while suppressing stack traces and system internals in non-testing environments.
- **A06:2021 — Vulnerable and Outdated Components:**
  - All dependencies pinned; no external unverified libraries introduced.
- **A07:2021 — Identification and Authentication Failures:**
  - Sanctum session authentication enforced on all appointment endpoints. Session user drives customer and dealership resolution.
- **A08:2021 — Software and Data Integrity Failures:**
  - `appointment_events` table maintains immutable chronological audit trail of all appointment state transitions.
- **A09:2021 — Security Logging and Monitoring Failures:**
  - Complete PII scrubbing from history payloads (`INV-05.7`); recursive filtering ensures phone numbers and email addresses are never leaked in history logs.
  - Metric M-05.2 query established to flag ID harvesting behavior.
- **A10:2021 — Server-Side Request Forgery (SSRF):**
  - No outbound HTTP requests or webhook calls initiated in the read query path.

---

## 5. Extensibility against Future Constraints

- **FC-05.1 (Search by plate or customer name):**
  - Trigram indexes exist on `vehicles.licence_plate` and `customers.full_name` (from F02).
  - Seam: `DealershipAppointmentsRequest` and `AppointmentRepository::listForDealershipDay` can accept an optional `$search` query parameter and append trigram similarity clauses.
- **FC-05.2 (Customer visibility into history):**
  - History event payloads are already fully sanitized of personal data.
  - Seam: Opening history to customers requires only updating the route authorization gate on `GET /api/v1/appointments/{appointment}/history`.
- **FC-05.3 ("Contact required" filter - F12):**
  - Seam: `DealershipAppointmentsRequest` already contains parameter validation for `contactRequired=true`, making `date` optional and querying flagged bookings from today onwards.

---

## 6. Verification & Quality Gates

- **Backend Tests:** 271 passing tests (2,248 assertions) on real PostgreSQL container, including acceptance suite `ViewAppointmentsAcceptanceTest` (M1–M6).
- **Pint:** Zero style issues across all PHP files.
- **PHPStan:** Level 8 static analysis passing with 0 errors across 272 files.
- **Frontend Vitest:** 35 test files passing (135 unit & component tests).
- **Frontend ESLint & TypeScript:** Passing with 0 errors.
