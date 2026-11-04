# Feature Review · `feature/09-reschedule`

Date: 2026-11-04  
Branch: `feature/09-reschedule`  
Spec: `docs/features/09-reschedule/`

---

## 1. Branch Review Overview

- **Diff Overview:** `git diff develop...HEAD` across 51 files (+4,513 / -79 lines).
- **Tickets Executed (01–10):**
  1. `01-prefactor-confirm-moveto-and-excludeappointmentid-are`: Confirmed reusability of `AppointmentWriter::moveTo` and `excludeAppointmentId` in candidate selection.
  2. `02-advisor-reschedules-an-appointment`: Tracer bullet implementing `POST /api/v1/appointments/{appointment}/reschedule`, advisor reschedule screen (`/staff/appointments/:id/reschedule`), gate `reschedule-appointment`, FormRequest validation, `SlotGrid` integration with `currentStart`.
  3. `03-choosing-the-new-pair`: Preference handling for keeping technician, fallback to next qualified technician when preferred is busy, vehicle clash detection, and self-slot movement.
  4. `04-version-and-retry-rules-on-reschedule`: Enforced 3-candidate retries on exclusion conflict `23P01` with same expected version, optimistic lock checks, and idempotent replay handling.
  5. `05-reschedule-rules`: Business rules enforcing `CONFIRMED` status prerequisite, rejection of same-time moves (`RESCHEDULE_SAME_TIME`), operating hours boundaries (`OUTSIDE_OPENING_HOURS`), and 90-day booking horizon.
  6. `06-customer-reschedules-api`: Extended reschedule endpoint to customer role, enforced 2-hour change window (`TOO_LATE_TO_RESCHEDULE`), stale version detection after cancellation (`APPOINTMENT_MODIFIED`), and cross-customer isolation.
  7. `07-reschedule-existing-booking-from-the-f02-card`: Enriched `VehicleAlreadyBooked` exception with existing booking status and added "Reschedule existing booking" button to `BookingErrorCard` when status is `CONFIRMED` and user is staff.
  8. `08-customer-change-time-screen`: Built mobile-first customer change time screen (`/my-bookings/:id/change`), 14-day date chips, `SlotGrid` with customer variant, 2-hour alert banner, and `AppointmentModifiedAlert` on 412.
  9. `09-parallel-changes-and-adversarial-input`: Concurrency tests for simultaneous cancel + reschedule and parallel reschedules via `Parallel::run(2, ...)`; integration tests covering all 12 rows of API §B4 adversarial input table.
  10. `10-acceptance`: End-to-end acceptance verification (M1–M6), BA metrics measurement, and whole-branch quality checks.

---

## 2. Spec Completeness

- **All Acceptance Criteria (AC-09.1 to AC-09.13):**
  - **AC-09.1 / INV-09.1 / FR-09.2:** Failed move leaves original appointment, version, and allocations untouched (`RescheduleTest::test_AC_09_1_failed_move_keeps_the_original`).
  - **AC-09.2 / INV-09.3:** Moving inside own slot or overlapping own start succeeds without self-exclusion clash (`RescheduleTest::test_AC_09_2_move_inside_own_slot`, `test_move_earlier_overlapping_own_slot`).
  - **AC-09.3:** Stale version returns 412 `APPOINTMENT_MODIFIED` with latest appointment state and ETag (`RescheduleTest::test_AC_09_3_stale_version_after_cancel`, `ChangeTimePage.test.tsx`).
  - **AC-09.4 / FR-09.3 / INV-09.4:** Preferred technician prioritized when `keepTechnician: true` (`RescheduleTest::test_AC_09_4_keeps_the_same_technician`).
  - **AC-09.5 / D-09.6:** When preferred technician is busy, next ranked qualified technician is allocated; technician ID matches active technician allocation (`RescheduleTest::test_AC_09_5_other_technician_when_preferred_is_busy`).
  - **AC-09.6 / D-09.10:** Conflicting vehicle booking at requested time returns 409 `VEHICLE_ALREADY_BOOKED` immediately (`RescheduleTest::test_AC_09_6_vehicle_busy_at_new_time`).
  - **AC-09.7 / BR-09.1 / D-09.3 / INV-09.5:** Non-confirmed statuses (`HELD`, `CHECKED_IN`, `CANCELLED`, `COMPLETED`) reject reschedule with 422 `INVALID_STATUS_TRANSITION` (`RescheduleTest::test_AC_09_7_only_confirmed_moves`).
  - **AC-09.8 / D-09.4 / INV-09.6:** Customer reschedule within 2-hour window returns 422 `TOO_LATE_TO_RESCHEDULE` with dealership phone; exactly 2 hours succeeds with 200 (`RescheduleTest::test_AC_09_8_customer_two_hour_rule`).
  - **AC-09.9 / D-09.5:** Moving to identical instant in any timezone offset returns 422 `RESCHEDULE_SAME_TIME` (`RescheduleTest::test_AC_09_9_same_time_is_rejected`).
  - **AC-09.10 / AC-04.6 / INV-09.2:** Parallel cancel and reschedule on same version yield one 200 and one 412; final version 4; zero allocation overlaps (`ParallelRescheduleTest::test_AC_04_6_cancel_and_reschedule_on_the_same_version`).
  - **AC-09.11 / INV-09.8:** Reschedule inserts 1 `RESCHEDULED` history row in `appointment_events` (with from/to timestamps and assignments) and 1 `AppointmentRescheduled` outbox event (`RescheduleTest::test_AC_09_11_history_and_outbox`).
  - **AC-09.12 / FR-09.4 / WEB-09.1:** Advisor Reschedule UI renders current appointment details, slot picker with current slot marked, handles 409/412 conflicts, and sends `If-Match` with `keepTechnician: true` (`ReschedulePage.test.tsx`).
  - **AC-09.13 / WEB-09.2 / WEB-09.3:** Customer Change Time screen passes `excludeAppointmentId` to availability query, shows current slot as "Current" and disabled, and handles 412 without automatic resend (`ChangeTimePage.test.tsx`).

- **Invariants Enforced (INV-09.1 to INV-09.9):**
  - **INV-09.1:** All-or-nothing move within a single database transaction (`DB::transaction`).
  - **INV-09.2:** Optimistic locking via `updateVersioned()` on the appointments table prevents lost updates.
  - **INV-09.3:** Deactivating old allocations before inserting new ones prevents self-clash on overlapping moves.
  - **INV-09.4:** Preferred technician prioritized in allocation strategy without violating skill level requirements.
  - **INV-09.5:** State machine permits reschedule only from `CONFIRMED`.
  - **INV-09.6:** Customer change window enforced strictly at 2 hours prior to scheduled start.
  - **INV-09.7:** Booking time rules (past, 90-day horizon, operating hours) enforced consistently.
  - **INV-09.8:** Transactional outbox pattern guarantees atomic event emission.
  - **INV-09.9:** Database foreign keys and denormalized technician/bay columns remain strictly synchronized with active allocations.

- **Business Analytics Metrics (§B2):**
  - **M-09.1:** Reschedules per day monitored via `appointment_events` where `type = 'RESCHEDULED'`.
  - **M-09.2:** Share of reschedules keeping preferred technician: 100% in test suite (Threshold: $\ge$ 80%).
  - **M-09.3:** Failed reschedules categorized by error code via `api_events`.

---

## 3. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `AppointmentWriter::moveTo`: Encapsulates atomic transition, allocation deactivation/activation, outbox emission, and exclusion violation retries.
  - `BookingService::reschedule`: Orchestrates domain business rules (status verification, customer 2-hour window, time boundary validation).
  - `RescheduleAppointmentRequest`: Handles HTTP input validation and timezone-aware 15-minute step verification.
  - `AppointmentController::reschedule`: Thin controller invoking service and returning API resource.
  - `ReschedulePage` & `ChangeTimePage`: Decoupled staff and customer UI components addressing distinct screen layouts and interactions.
- **Open/Closed Principle (OCP):**
  - `AppointmentWriter` and `BookingService` support additional resource types or scheduling rules without mutating existing database schemas.
- **Liskov Substitution Principle (LSP):**
  - `AppointmentWriter` and `BookingService` fully satisfy `AppointmentWriterInterface` and `BookingServiceInterface`.
- **Interface Segregation Principle (ISP):**
  - Domain interfaces expose concise method contracts specific to booking mutation.
- **Dependency Inversion Principle (DIP):**
  - Controllers depend exclusively on `BookingServiceInterface` resolved via Laravel dependency injection container and `DomainServiceProvider`.

---

## 4. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - Route protected by `auth:sanctum` and Gate `reschedule-appointment`.
  - Advisors can only reschedule appointments belonging to their own dealership.
  - Customers can only reschedule their own appointments; requests for other customers' appointments return 404 `APPOINTMENT_NOT_FOUND` via `findVisibleTo`.
  - Foremen and Admins receive 403 `FORBIDDEN`.
- **A02:2021 — Cryptographic Failures:**
  - No credentials, tokens, or PII exposed in query parameters or event payloads.
- **A03:2021 — Injection:**
  - Eloquent ORM and parameterized queries utilized throughout; raw range statements use safe parameter bindings.
- **A04:2021 — Insecure Design:**
  - Double protection against race conditions: PostgreSQL EXCLUDE constraint eliminates overlapping allocations; optimistic locking prevents lost updates.
- **A05:2021 — Security Misconfiguration:**
  - Problem Details (RFC 9457) returned across all error paths without internal stack traces.
- **A06:2021 — Vulnerable and Outdated Components:**
  - No new external packages introduced.
- **A07:2021 — Identification and Authentication Failures:**
  - Customer identity resolved strictly from authenticated session (`auth()->user()`).

---

## 5. Extensibility against Future Constraints

- **FC-09.1 (Change service type during a move):**
  - `moveTo` accepts explicit `start` and `end` timestamps; expanding input to accept altered service types and durations cleanly integrates into candidate selection.
- **FC-09.2 (Release expired holds blocking new time):**
  - Allocation candidate finder integrates `excludeAppointmentId` and hold expiration sweep without architectural refactoring.
- **FC-09.3 (Per-dealership change window):**
  - Customer change window logic isolated in `CustomerChangePolicy` and `changeWindow.ts`, ready for dealership-specific configuration overrides.

---

## 6. Design Pass Checks

1. **One decision, one seam:** Route authorization centralized in `reschedule-appointment` Gate; time formatting handled via `formatInZone`; optimistic concurrency managed via `updateVersioned()`.
2. **Read once, decide on that row:** Appointment state and version validated inside write transaction with row locking.
3. **Bounded:** Date selection restricted to 14 days on customer screen; 90-day maximum booking horizon enforced on backend.
4. **Adversarial inputs:** Fully tested in `RescheduleAdversarialInputTest` covering malformed timestamps, unaligned minutes, past dates, closed days, invalid types, and missing headers.
5. **Migrations meet real data:** EXCLUDE constraints and version column verified under real PostgreSQL concurrency.
6. **Extract before you copy:** Reused `BookingErrorCard`, `SlotGrid`, `AppointmentModifiedAlert`, and `SubmitButton`.
7. **Copy from one place:** All strings sourced from `staff/appointments/copy.ts` and `customer/bookings/copy.ts`.

---

## 7. Findings & Verification Verdict

- **Automated Tests:**
  - Backend: 525 passed (4,811 assertions), 0 failures.
  - Frontend: 45 test files passed (200 tests), 0 failures.
  - Static Analysis: Pint clean (364 files); PHPStan clean (Level 6, 0 errors); ESLint & TypeScript clean.
- **Manual Scenarios (M1–M6):** All verified PASS in `6-test-case.md` §A2.
- **Verdict:** **READY TO MERGE** into `develop`.
