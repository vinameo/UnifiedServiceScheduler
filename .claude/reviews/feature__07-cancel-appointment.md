# Feature Review · `feature/07-cancel-appointment`

Date: 2026-11-04  
Branch: `feature/07-cancel-appointment`  
Spec: `docs/features/07-cancel-appointment/`

---

## 1. Branch Review Overview

- **Diff Overview:** `git diff develop...HEAD` across 43 files (+2,883 / -80 lines).
- **Tickets Executed (01–08):**
  1. `01-prefactor-customerchangepolicy`: Created reusable `CustomerChangePolicy` in domain (`CustomerChangePolicy.php` and `changeWindow.ts`), enforcing the $\ge$ 2-hour window rule for `CONFIRMED` appointments while permitting holds (`HELD`) without time restrictions.
  2. `02-advisor-cancels-an-appointment`: Tracer bullet introducing `CancelReason` enum (forStaff / forCustomer), `TooLateToCancel` problem exception, `BookingService::cancel()`, `CancelAppointmentRequest` validation, `POST /appointments/{appointment}/cancel`, `CancelAppointmentModal` (Mantine Modal with released slot details and `SubmitButton`), and appointment details layout integration.
  3. `03-cancel-rules-for-staff`: Added cancel business rules for staff: reason required (400), idempotent repeat cancellation of cancelled appointment returns 200 without extra events/outbox (`AC-07.2`), `COMPLETED` cannot be cancelled (422 `INVALID_STATUS_TRANSITION`), and role authorization via Gate (`SERVICE_ADVISOR` and `CUSTOMER` allowed; `WORKSHOP_FOREMAN` and `ADMIN` forbidden with 403).
  4. `04-versions-history-and-replay-on-cancel`: Enforced optimistic locking via `If-Match` / `ETag` (stale -> 412 `APPOINTMENT_MODIFIED`, missing -> 428 `PRECONDITION_REQUIRED`), checked non-cancellable terminal statuses (`CHECKED_IN`, `IN_PROGRESS`, `NO_SHOW`, `EXPIRED` -> 422), verified idempotent replay with identical key returns cached 200 with `Idempotent-Replayed: true`, and added web component test for 412 conflict alert.
  5. `05-customer-cancels-api`: Implemented customer cancellation API rules: < 2 hours returns 422 `TOO_LATE_TO_CANCEL` with dealership phone, cross-customer cancel returns 404 `APPOINTMENT_NOT_FOUND`, outbox event only produced for `CONFIRMED` (0 for `HELD`), exact 2-hour boundary behavior, and privacy masking ensuring customer `GET /appointments/{id}` never leaks internal staff notes or reasons.
  6. `06-customer-cancel-sheet-and-banners`: Implemented customer `CancelBookingSheet` (Mantine Drawer with optional reasons and note on `OTHER`), conditionally replaced Cancel button with call notice when < 2 hours before appointment (`TC-07-16`), and rendered dealership or customer cancellation banner when cancelled.
  7. `07-parallel-cancels-and-adversarial-input`: Verified 5 parallel processes cancelling with `If-Match: "1"` all succeed with 200 `CANCELLED`, raising version by exactly 1 with 1 history row and 1 outbox event (`AC-07.9`), and verified all 11 adversarial input rows from API §B4 table (`TC-07-17`).
  8. `08-acceptance`: Validated all manual acceptance scenarios M1–M6 (`TC-07-20`), recorded results in `6-test-case.md`, measured BA metrics, and completed whole-branch review.

---

## 2. Spec Completeness

- **All Acceptance Criteria (AC-07.1 to AC-07.13):**
  - **AC-07.1 / INV-07.1 / INV-07.6:** Advisor cancels `CONFIRMED` appointment with reason and note; returns 200 with `ETag: "2"`, updates audit columns (`cancel_reason`, `cancel_note`, `cancelled_at`, `cancelled_by_user_id`), deactivates all 3 active resource allocations, and frees slot immediately for subsequent bookings (`CancelAppointmentTest::test_AC_07_1_cancel_frees_the_slot_at_once`).
  - **AC-07.2 / D-07.4:** Cancelling an already cancelled appointment with current or earlier `If-Match` returns 200 with current state without emitting additional history rows or outbox messages (`CancelAppointmentTest::test_AC_07_2_cancelling_twice_returns_current_state`).
  - **AC-07.3 / BR-07.1 / INV-07.2:** Completed appointments cannot be cancelled, returning 422 `INVALID_STATUS_TRANSITION` (`CancelAppointmentTest::test_AC_07_3_completed_cannot_be_cancelled`).
  - **AC-07.4 / BR-07.2 / INV-07.3:** Customer cancelling inside 2 hours gets 422 `TOO_LATE_TO_CANCEL` with dealership phone, leaving appointment `CONFIRMED` (`CancelAppointmentTest::test_AC_07_4_customer_too_late`).
  - **AC-07.5:** Service advisors have no time restriction and can cancel appointments starting in under 2 hours (`CancelAppointmentTest::test_AC_07_5_advisor_has_no_time_limit`).
  - **AC-07.6 / D-07.1:** Staff cancellations require a valid `reason` code; missing reason returns 400 `VALIDATION_ERROR` (`CancelAppointmentTest::test_AC_07_6_staff_reason_required`).
  - **AC-07.7 / INV-07.4:** Customer attempting to cancel another customer's booking receives 404 `APPOINTMENT_NOT_FOUND`, preventing ID disclosure (`CancelAppointmentTest::test_AC_07_7_cannot_cancel_another_customers_booking`).
  - **AC-07.8 / D-07.2:** Workshop foremen and admins are forbidden from cancelling appointments, returning 403 `FORBIDDEN` via Gate (`CancelAppointmentTest::test_AC_07_8_foreman_and_admin_cannot_cancel`).
  - **AC-07.9 / D-07.5 / INV-07.5:** 5 parallel cancel requests with the same version all return 200 `CANCELLED`, version rises by exactly 1, exactly 1 `CANCELLED` history row is recorded, and 1 outbox event is created (`ParallelCancelTest::test_AC_07_9_parallel_cancels_all_succeed_once`).
  - **AC-07.10 / D-07.6:** Outbox notification is only generated for `CONFIRMED` appointment cancellations; cancelling a `HELD` booking produces 0 outbox events (`CancelAppointmentTest::test_AC_07_10_outbox_only_for_confirmed`).
  - **AC-07.11 / D-07.7:** Missing `If-Match` returns 428 `PRECONDITION_REQUIRED`; stale `If-Match` returns 412 `APPOINTMENT_MODIFIED` with fresh data (`CancelAppointmentTest::test_AC_07_11_stale_and_missing_if_match`).
  - **AC-07.12 / WEB-07.2:** Staff modal displays bay, technician, and slot time range, requires reason selection, and renders `SubmitButton` ("Cancelling…") (`CancelAppointmentModal.test.tsx`).
  - **AC-07.13 / WEB-07.4:** Customer details view hides Cancel button when inside 2 hours and renders "It's less than 2 hours before your appointment. Please call {phone} to cancel." with `tel:` link (`BookingDetailsPage.test.tsx`).

- **Invariants Enforced (INV-07.1 to INV-07.6):**
  - **INV-07.1:** Slot freed immediately: allocations deactivated in same transaction.
  - **INV-07.2:** Only `CONFIRMED` and `HELD` appointments can be cancelled.
  - **INV-07.3:** Customer cancellation window enforced at $\ge$ 2 hours for `CONFIRMED`.
  - **INV-07.4:** Scoping strictly enforced via `findVisibleTo`.
  - **INV-07.5:** Optimistic concurrency: exactly one version increment and one event per cancellation.
  - **INV-07.6:** Full audit trail: reason, note, timestamp, and user ID saved.

- **Business Analytics Metrics (§B2):**
  - **M-07.1:** Cancellations by reason and channel trackable via `cancel_reason` and `source`.
  - **M-07.2:** Customers blocked for being too late trackable via `TOO_LATE_TO_CANCEL` problem code.
  - **M-07.3:** Cancelled slots rebooked trackable by comparing overlapping allocations created after `cancelled_at`.

---

## 3. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `CustomerChangePolicy`: Encapsulates purely the rules for customer changes/cancellations.
  - `CancelReason`: Value enum separating staff and customer reason definitions and validation.
  - `BookingService::cancel()`: Orchestrates authorization, policy verification, transition, and concurrent conflict recovery.
  - `AppointmentController::cancel()`: Thin HTTP adapter handling request validation and resource response formatting.
  - `CancelAppointmentModal` / `CancelBookingSheet`: Separated presentation components tailored for staff desktop and customer mobile workflows.
- **Open/Closed Principle (OCP):**
  - Domain policies and cancel reasons are extensible for future fee calculations (`FC-07.1`) and dealership-specific limits (`FC-07.2`) without rewriting core scheduling primitives.
- **Liskov Substitution Principle (LSP):**
  - `BookingService` adheres to `BookingServiceInterface` signature and semantics.
- **Interface Segregation Principle (ISP):**
  - Cancellation methods cleanly exposed without coupling to unrelated booking creation or master data mutations.
- **Dependency Inversion Principle (DIP):**
  - Controllers and services type-hint interfaces (`AppointmentRepositoryInterface`, `BookingServiceInterface`, `AppointmentTransitionInterface`) injected via Laravel container.

---

## 4. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - Route protected by `auth:sanctum` and Gate `can:cancel-appointment`.
  - Gate permits only `SERVICE_ADVISOR` and `CUSTOMER`; `WORKSHOP_FOREMAN` and `ADMIN` rejected with 403.
  - Customer appointment lookups use `findVisibleTo`, ensuring a customer attempting to access another customer's booking receives 404, never 403 or data leakage.
  - Internal cancellation notes and staff-selected reasons are masked for customers in `AppointmentResource`, preventing exposure of internal comments.
- **A02:2021 — Cryptographic Failures:**
  - UUIDv4 used for all entity references.
- **A03:2021 — Injection:**
  - Eloquent and parameterized PDO queries used exclusively; no raw string concatenation in SQL queries.
- **A04:2021 — Insecure Design:**
  - Optimistic locking (`If-Match`) prevents race conditions between concurrent customer reschedules and advisor cancellations.
  - `Idempotency-Key` prevents duplicate processing on network retries.
  - Customer 2-hour change window enforced strictly server-side regardless of client clock skew.
- **A05:2021 — Security Misconfiguration:**
  - All error responses strictly formatted per RFC 9457 Problem Details (`application/problem+json`).
- **A06:2021 — Vulnerable and Outdated Components:**
  - Zero external packages added; all components built with existing project dependencies.
- **A07:2021 — Identification and Authentication Failures:**
  - Uses existing Sanctum session cookie authentication.

---

## 5. Extensibility against Future Constraints

- **FC-07.1 (Cancellation fees):**
  - `BookingService::cancel()` returns the updated `Appointment` model where fee calculations and transactional payment capture can hook into the transition transaction before commit.
- **FC-07.2 (Per-dealership time limits):**
  - `CustomerChangePolicy::isWithinCustomerChangeWindow` accepts `int $hours = 2`, which can seamlessly receive dealership-configured threshold `dealership.cancel_window_hours` in future increments.

---

## 6. Design Pass Checks

1. **One decision, one seam:** Customer change window rule is centralized in `CustomerChangePolicy.php` on the backend and `changeWindow.ts` on the frontend; appointment lookups routed through `findVisibleTo`.
2. **Read once, decide on that row:** Cancellation checks status and version inside the update transaction via `updateVersioned()` with row-level locking.
3. **Bounded:** Input `note` bounded to $\le 500$ characters; reason bounded to predefined enum values.
4. **Adversarial inputs:** All 11 combinations tested and verified in `CancelAdversarialInputTest`.
5. **Migrations meet real data:** No schema changes required; existing `appointments` and `resource_allocations` tables satisfied all requirements.
6. **Extract before you copy:** Shared copy definitions in `copy.ts` and common formatters reused.
7. **Copy from one place:** All user-facing strings loaded from area `copy.ts`.

---

## 7. Findings & Verification Verdict

- **Automated Tests:**
  - Backend: 29 cancel feature tests passed (203 assertions). Full suite passes cleanly.
  - Frontend: 42 test files, 176 tests passed. ESLint and TypeScript checks pass with 0 errors.
  - Code Style & Static Analysis: Pint passed; PHPStan analysis clean (Level 6, 0 errors).
- **Manual Scenarios (M1–M6):** All verified PASS.
- **Verdict:** **READY TO MERGE** into `develop`.

