# Feature Review · `feature/10-temporary-hold`

Date: 2026-11-04  
Branch: `feature/10-temporary-hold`  
Spec: `docs/features/10-temporary-hold/`

---

## 1. Branch Review Overview

- **Diff Overview:** `git diff develop...HEAD` across 63 files (+4,951 / -160 lines).
- **Tickets Executed (01–11):**
  1. `01-prefactor-expiredholdreleaser-and-useserverclock`: Added `ExpiredHoldReleaserInterface` + `ExpiredHoldReleaser` domain service seam; added frontend `useServerClock` hook calculating server clock offset via response `Date` header.
  2. `02-customer-holds-a-time-and-confirms-it`: Tracer bullet introducing `mode: HOLD` in `POST /api/v1/appointments`, new endpoint `POST /api/v1/appointments/{appointment}/confirm`, gate `confirm-hold`, `BookingService::confirmHold`, and customer `/book/hold/:id` page.
  3. `03-hold-rules`: Business rules rejecting staff holds with 422 `HOLD_NOT_ALLOWED`, enforcing uppercase `HOLD`, handling optional `vehicleId` during hold (TECH + BAY allocations without VEHICLE), and adversarial input coverage.
  4. `04-confirm-rules`: Enforced hold confirmation rules: required `vehicleId`, 410 `HOLD_EXPIRED` on expired holds, 409 `VEHICLE_ALREADY_BOOKED` on vehicle conflict (hold kept), 404 for other customer's vehicle or appointment, 412 on stale version, boundary testing at exact expiry, and idempotent replays.
  5. `05-expired-hold-screen`: Customer web expired state on `/book/hold/:id` when countdown reaches 00:00 or confirm returns 410/412 (`status: EXPIRED`), red timer under 1 minute, and "Pick a time again" keeping entered service and vehicle draft.
  6. `06-one-hold-per-customer`: Migration adding partial unique index `appointments_one_hold_per_customer` on `(customer_id) WHERE status = 'HELD'`, customer row locking (`SELECT ... FOR UPDATE`) during hold creation, and atomic transition of previous hold to `EXPIRED`.
  7. `07-parallel-holds-by-one-customer`: Parallel concurrency tests verifying multiple simultaneous hold requests by one customer with or without an existing hold result in 201 with exactly one `HELD` appointment.
  8. `08-holds-expire-job`: Command `php artisan holds:expire [--now=<ISO>]` running every 30 seconds (`everyThirtySeconds()->withoutOverlapping()`), expiring holds in batches of up to 500 via versioned transition and deactivating allocations.
  9. `09-free-expired-holds-in-the-booking-flow`: Wired `ExpiredHoldReleaser` into `CreateBooking` (F02, both modes) and `RescheduleAppointment` (F09), deactivating blocking expired holds immediately before candidate selection.
  10. `10-expire-and-confirm-race`: Concurrency verification across 100 parallel runs with real CLI processes testing race between `holds:expire --now=T+1s` and customer confirm at `T - 300ms`, proving zero mixed states and zero allocation overlaps.
  11. `11-acceptance`: End-to-end acceptance verification (M1–M6), BA metrics measurement, and whole-branch quality checks.

---

## 2. Spec Completeness

- **All Acceptance Criteria (AC-10.1 to AC-10.12):**
  - **AC-10.1 / FR-10.1 / D-10.3:** Customer taps "Hold and continue" -> 201 `AppointmentResource` (`status HELD`, `expiresAt` = +5 min), 2 active allocations (`TECHNICIAN`, `BAY`), countdown starts at 05:00 (`HoldTest::test_AC_10_1_hold_blocks_the_slot`, `HoldPage.test.tsx`).
  - **AC-10.2 / FR-10.3 / D-10.6:** Confirming a hold created > 5 minutes ago returns 410 `HOLD_EXPIRED`, transitions status to `EXPIRED`, and deactivates allocations (`ConfirmHoldTest::test_AC_10_2_expired_hold_cannot_be_confirmed`).
  - **AC-10.3 / FR-10.4 / INV-10.3 / D-10.7:** Expiry job and confirm running concurrently across 100 runs always resolve with exactly one winner (`CONFIRMED` with 3 active allocations or `EXPIRED` with 0 active allocations); losing confirm gets 412 `APPOINTMENT_MODIFIED` (`ParallelHoldExpiryTest::test_AC_10_3_job_and_confirm_never_both_win`).
  - **AC-10.4 / INV-10.1:** Held slot blocks technician and bay from other customers; availability query marks slot unavailable and booking returns 409 `NO_AVAILABILITY` (`HoldTest::test_AC_10_4_held_slot_is_not_free_for_others`).
  - **AC-10.5 / FR-10.6 / INV-10.2:** Customer holding 10:00 then holding 11:00 ends 10:00 hold as `EXPIRED` (allocations inactive) and sets 11:00 as `HELD` in the same transaction (`HoldTest::test_AC_10_5_new_hold_ends_the_old_one`).
  - **AC-10.6 / D-10.4:** Customer holding 10:00 fails to hold 11:00 due to unavailability (409) -> previous 10:00 hold remains `HELD` with unchanged version (`HoldTest::test_AC_10_6_failed_new_hold_keeps_the_old_one`).
  - **AC-10.7 / INV-10.4 / D-10.10:** Valid customer confirm transitions hold to `CONFIRMED`, clears `expires_at`, inserts active `VEHICLE` allocation (total 3 active allocations), writes 1 `AppointmentConfirmed` outbox event, and logs `CONFIRMED` event (`ConfirmHoldTest::test_AC_10_7_confirm_adds_the_vehicle`).
  - **AC-10.8 / D-10.2:** Confirming with a vehicle already booked at the held time returns 409 `VEHICLE_ALREADY_BOOKED` and keeps the hold `HELD` at version 1 (`ConfirmHoldTest::test_AC_10_8_vehicle_clash_keeps_the_hold`).
  - **AC-10.9 / FR-10.5 / INV-10.6 / D-10.9:** Expired hold blocking only technician is cleared on the fly by `ExpiredHoldReleaser` during subsequent customer booking without waiting for the cron job, returning 201 (`ExpiredHoldReleaserTest::test_AC_10_9_booking_flow_frees_expired_holds`).
  - **AC-10.10 / D-10.8 / INV-10.5:** Command `holds:expire` transitions 3 expired holds to `EXPIRED` with inactive allocations while leaving 1 valid hold untouched (`ExpireHoldsCommandTest::test_AC_10_10_job_expires_only_expired_holds`).
  - **AC-10.11 / D-10.1:** Staff sending `mode: HOLD` receives 422 `HOLD_NOT_ALLOWED` (`HoldTest::test_AC_10_11_staff_cannot_hold`).
  - **AC-10.12:** Temporary hold screen at countdown 00:00 or receiving 410/412 renders "Your hold has expired" and "Pick a time again", preserving draft vehicle and service (`HoldPage.test.tsx`).

- **Invariants Enforced (INV-10.1 to INV-10.8):**
  - **INV-10.1:** Held appointments have active allocations; PostgreSQL EXCLUDE constraint prevents overlaps.
  - **INV-10.2:** At most one `HELD` appointment per customer enforced via customer row locking (`FOR UPDATE`) and partial unique index `appointments_one_hold_per_customer`.
  - **INV-10.3:** Hold finishes in exactly one terminal state; version check prevents double-resolution.
  - **INV-10.4:** Hold confirmation requires `status = HELD`, `expires_at > now`, matching version, and customer vehicle ownership; activates `VEHICLE` allocation atomically.
  - **INV-10.5:** Hold expiration deactivates all allocations in the same transaction.
  - **INV-10.6:** Expired holds do not block scheduling; `ExpiredHoldReleaser` cleans blocking holds ahead of candidate selection.
  - **INV-10.7:** Only hold owner can confirm; non-owner access returns 404; staff access returns 403.
  - **INV-10.8:** All expiration checks use Carbon application clock passed as explicit parameter.

- **Business Analytics Metrics (§B2):**
  - **M-10.1:** Share of holds expired in last 7 days: measured 1.0 (baseline in test stack; threshold Watch, > 40% -> review flow).
  - **M-10.2:** Holds expired late (job lag): measured 00:00:08 (8 seconds, well below < 60 s threshold).
  - **M-10.3:** Holds still `HELD` past expiry now: measured 0 (scheduler container active every 30s; threshold 0).

---

## 3. SOLID Architecture

- **Single Responsibility Principle (SRP):**
  - `ExpiredHoldReleaser`: Dedicated strictly to discovering and releasing blocking expired holds in a separate transaction.
  - `ExpireHoldsCommand`: Orchestrates scheduled batch expiration of expired holds.
  - `BookingService::confirmHold`: Encapsulates domain logic for validating and confirming a hold.
  - `ConfirmHoldRequest`: Handles request validation (UUID, reminder flag).
  - `HoldPage` & `useCountdown`: Frontend separation of countdown timer state from hold form UI.
- **Open/Closed Principle (OCP):**
  - `ExpiredHoldReleaserInterface` can be adapted to other resource types or future scheduling policies without altering booking core.
  - Support for staff holds (FC-10.1) can be introduced simply by updating the permission check in `BookingService::create`.
- **Liskov Substitution Principle (LSP):**
  - `ExpiredHoldReleaser` and `BookingService` satisfy their respective interfaces.
  - All exceptions inherit from `ProblemException` and render RFC 9457 problem details.
- **Interface Segregation Principle (ISP):**
  - `ExpiredHoldReleaserInterface` declares only `releaseBlocking()`.
  - `BookingServiceInterface` declares distinct methods (`create`, `confirmHold`, `cancel`, `reschedule`).
- **Dependency Inversion Principle (DIP):**
  - Controllers depend on interfaces (`BookingServiceInterface`); services depend on `AppointmentRepositoryInterface`, `ExpiredHoldReleaserInterface`, `ResourceAllocationRepositoryInterface`, bound in `DomainServiceProvider`.

---

## 4. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - `confirm-hold` ability registered as Gate restricting confirm endpoint to `CUSTOMER` role.
  - Hold confirmation uses `findVisibleTo` ensuring customers cannot view or confirm other customers' holds (returns 404 `APPOINTMENT_NOT_FOUND`).
  - Vehicle ownership verified against authenticated customer (returns 404 `VEHICLE_NOT_FOUND` if vehicle belongs to another customer).
  - Staff sending `mode: HOLD` rejected with 422 `HOLD_NOT_ALLOWED`.
  - Staff attempting to confirm hold rejected with 403 `FORBIDDEN`.
- **A02:2021 — Cryptographic Failures:**
  - No customer PII, secrets, or tokens exposed in responses, query parameters, or event logs.
- **A03:2021 — Injection:**
  - Parameterized queries and Eloquent ORM used exclusively; raw SQL in migration and queries use bound variables.
- **A04:2021 — Insecure Design:**
  - Overlap prevention backed by PostgreSQL EXCLUDE constraint.
  - Concurrency defended by optimistic locking (`updateVersioned`) and customer row locking (`FOR UPDATE`).
  - Idempotent request replay via `Idempotency-Key` prevents duplicate appointment creations or confirmations.
- **A05:2021 — Security Misconfiguration:**
  - Problem Details (RFC 9457) returned across all error paths without internal stack traces or database schema leaks.
- **A06:2021 — Vulnerable and Outdated Components:**
  - Zero external packages added; existing dependencies verified.
- **A07:2021 — Identification and Authentication Failures:**
  - Customer identity resolved strictly from authenticated session (`auth()->user()`), ignoring any client-supplied customer ID.
- **A08:2021 — Software and Data Integrity Failures:**
  - Migration includes pre-check preventing partial unique index creation if duplicate holds exist; clean rollback in `down()`.
  - `appointment_events` remains append-only; `appointments.version` rises monotonically.
- **A09:2021 — Logging and Monitoring Failures:**
  - Correlation IDs attached to all request logs and problem details; PII redacted from logs.
- **A10:2021 — Server-Side Request Forgery (SSRF):**
  - No user-supplied URLs fetched or called.

---

## 5. Extensibility against Future Constraints

- **FC-10.1 (Staff holds while on the phone):**
  - The check rejecting staff holds is isolated in `HoldNotAllowed` validation in `BookingService::create`. Lifting this restriction for advisors or staff requires updating that gate/check without altering booking core or allocation algorithms.
- **FC-10.2 (Per-dealership hold length):**
  - `BookingService::create` can easily read `dealership.hold_minutes ?? config('app.hold_minutes')` because dealership context is already loaded.
- **FC-10.3 (Alert when the job stops):**
  - Metric query M-10.3 (`SELECT count(*) FROM appointments WHERE status = 'HELD' AND expires_at < now() - interval '2 minutes'`) is ready for integration into the F08 monitoring summary endpoint or Prometheus alert rules.

---

## 6. Design Pass Checks

1. **One decision, one seam:** Hold confirmation authorization handled via Gate `confirm-hold`; time synchronization handled via `useServerClock`; hold release logic encapsulated in `ExpiredHoldReleaser`.
2. **Read once, decide on that row:** Customer row locked `FOR UPDATE` on hold creation; version check on `updateVersioned()` protects confirm and expiry transitions.
3. **Bounded:** Expiry job bounded to 500 holds per run; one hold per customer strictly enforced.
4. **Adversarial inputs:** Fully covered in `HoldAdversarialInputTest` (missing vehicle ID, invalid UUID, wrong customer vehicle, advisor mode hold, lowercase hold mode, extra fields, missing If-Match).
5. **Migrations meet real data:** Migration pre-check verifies zero duplicate holds before applying unique index; index verified under parallel execution.
6. **Extract before you copy:** Reused `BookingDraft`, `ProblemAlert`, `SlotGrid`, `SubmitButton`, and domain models.
7. **Copy from one place:** All user-facing copy sourced from `customer/booking/copy.ts` and `errorCopy.ts`.

---

## 7. Findings & Verification Verdict

- **Automated Tests:**
  - Backend: 562 passed (6,135 assertions), 0 failures.
  - Frontend: 47 test files passed (211 tests), 0 failures.
  - Static Analysis: Pint clean (379 files); PHPStan clean (Level 6, 0 errors); ESLint & TypeScript clean.
- **Manual Scenarios (M1–M6):** All verified PASS in `6-test-case.md` §A2.
- **Verdict:** **READY TO MERGE** into `develop`.
