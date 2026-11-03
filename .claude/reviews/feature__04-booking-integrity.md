# Feature Review · `feature/04-booking-integrity`

Date: 2026-11-03  
Branch: `feature/04-booking-integrity`  
Spec: `docs/features/04-booking-integrity/`

---

## 1. Spec Completeness

- **All Acceptance Criteria (AC-04.1 to AC-04.24):**
  - AC-04.1: PostgreSQL GiST EXCLUDE constraint ensures exactly 1 of 20 parallel booking attempts wins (`ParallelBookingTest::test_AC_04_1_only_one_of_twenty_parallel_bookings_wins`).
  - AC-04.2: 2 candidate pairs across 10 concurrent requests yield exactly 2 bookings on different technicians and bays (`ParallelBookingTest::test_AC_04_2_two_pairs_give_exactly_two_bookings`).
  - AC-04.3: Same idempotency key + same canonical payload replays saved 201 response with `Idempotent-Replayed: true` and dispatches `IdempotentReplayed` (`IdempotencyTest::test_AC_04_3_same_key_same_body_replays`).
  - AC-04.4: Same idempotency key + different payload returns 422 `IDEMPOTENCY_KEY_MISMATCH` (`IdempotencyTest::test_AC_04_4_same_key_different_body_is_422`).
  - AC-04.5 / D-04.12: Saved 409 responses are replayed and do not re-execute booking (`IdempotencyTest::test_AC_04_5_saved_409_is_replayed`).
  - AC-04.6: Optimistic locking with `If-Match: "3"` on concurrent touches yields one 200 (`ETag: "4"`) and one 412 (`ParallelVersionTest::test_AC_04_6_two_changes_on_same_version_one_wins`).
  - AC-04.7: Mutating request without `If-Match` returns 428 `PRECONDITION_REQUIRED` (`IfMatchTest::test_AC_04_7_missing_if_match_is_428`).
  - AC-04.8: Vehicle conflict causes instant 409 `VEHICLE_ALREADY_BOOKED` with conflicting appointment details, without technician retries (`AppointmentWriterTest::test_AC_04_8_vehicle_clash_fails_at_once`).
  - AC-04.9 / INV-04.2: All-or-nothing transactional boundary: failure to insert allocations rolls back appointment creation completely (`AppointmentWriterTest::test_AC_04_9_all_or_nothing`).
  - AC-04.10 / D-04.16: Stale candidate pair conflict on technician or bay retries next candidate pair (`AppointmentWriterTest::test_AC_04_10_stale_pair_retries_next`).
  - AC-04.11: Maximum 3 candidate pair attempts before throwing `NoAvailability` with `SLOT_TAKEN` (`AppointmentWriterTest::test_AC_04_11_three_tries_then_slot_taken`).
  - AC-04.12 / D-04.10: 10 concurrent requests with identical key produce exactly 1 execution and 9 in-progress / replayed responses (`ParallelIdempotencyTest::test_AC_04_12_same_key_in_parallel_books_once`).
  - AC-04.13 / D-04.7: Database trigger enforces version increments on appointment updates (`VersionTriggerTest::test_AC_04_13_update_without_increment_is_rejected`).
  - AC-04.14 / INV-04.5 / INV-04.6: Freeing statuses (`CANCELLED`, `EXPIRED`, `NO_SHOW`, `COMPLETED`) deactivate active resource allocations (`TransitionTest::test_AC_04_14_freeing_statuses_release_allocations`).
  - AC-04.15 / D-04.8: Complete status transition graph implemented in `AppointmentStatus::canMoveTo()` (`AppointmentStatusTest::test_AC_04_15_transition_table`).
  - AC-04.16 / D-04.11: Idempotency keys are strictly scoped per authenticated user (`IdempotencyTest::test_AC_04_16_keys_are_per_user`).
  - AC-04.17: 5xx server errors do not save idempotency responses, allowing subsequent retries to re-execute (`IdempotencyTest::test_AC_04_17_server_errors_are_not_saved`).
  - AC-04.18 / D-04.13: Stale `IN_PROGRESS` locks (`locked_until < now()`) are taken over atomically (`IdempotencyTest::test_AC_04_18_stale_lock_is_taken_over`).
  - AC-04.19: Artisan command `idempotency:prune` purges expired keys (`IdempotencyTest::test_AC_04_19_expired_keys_are_pruned_and_ignored`).
  - AC-04.20 / D-04.4: Outbox entries commit atomically in the same transaction as the booking write (`OutboxWriterTest::test_AC_04_20_outbox_rows_commit_with_the_booking`).
  - AC-04.21 / D-03.7 / INV-04.13: Expired holds (`expires_at < now()`) are excluded from busy intervals (`BusyIntervalReaderTest::test_AC_04_21_expired_holds_are_not_busy`).
  - AC-04.22 / INV-04.4: 412 `APPOINTMENT_MODIFIED` carries latest appointment resource in problem details body and latest `ETag` (`IfMatchTest::test_AC_04_22_412_carries_latest_data`).
  - AC-04.23 / INV-04.14: `_testing` routes and commands are strictly disabled outside of `APP_ENV=testing` (`TestingRoutesTest::test_AC_04_23_testing_routes_only_in_testing`).
  - AC-04.24 / INV-04.15: Technicians disabled during candidate selection are locked `FOR SHARE` and never allocated (`AppointmentWriterTest::test_INV_04_15_disabled_technician_is_never_allocated`).

- **All Invariants (INV-04.1 to INV-04.15):**
  - INV-04.1: Database GiST EXCLUDE constraint rejects active overlaps for technician, bay, and vehicle (`ExclusionConstraintTest`).
  - INV-04.2: Full ACID all-or-nothing guarantee on booking creation (`AppointmentWriterTest::test_AC_04_9_all_or_nothing`).
  - INV-04.3: Version starts at 1, increments by 1 on every update.
  - INV-04.4: 412 carries latest data and `ETag`.
  - INV-04.5: Deactivation of allocations sets `active = false` and `deactivated_at = now`.
  - INV-04.6: Exactly 2 or 3 active allocations per active appointment; 0 for inactive.
  - INV-04.7: `moveTo` updates version and deactivates old allocations before inserting new allocations, preventing self-clash (`AppointmentWriterTest::test_INV_04_7_move_frees_old_before_taking_new`).
  - INV-04.8: Middleware ordering: `idempotent` runs before `if-match` (`MiddlewareOrderTest::test_INV_04_8_idempotency_runs_before_if_match`).
  - INV-04.9: Stale version check beats invalid transition check (412 before 422) (`TransitionTest::test_stale_version_beats_invalid_transition`).
  - INV-04.10: Replay returns saved response and dispatches `IdempotentReplayed`.
  - INV-04.11: Exactly one `appointment_events` row recorded per write (`AppointmentEventsTest::test_INV_04_11_one_event_per_write`).
  - INV-04.12: `OutboxWriter::append` throws `LogicException` if executed outside an active transaction (`OutboxWriterGuardTest`).
  - INV-04.13: `AllocationSnapshotRepository` ignores expired holds.
  - INV-04.14: `TestingRoutesServiceProvider` loads only in testing environment.
  - INV-04.15: Active check with row-level shared lock prevents allocating disabled technicians.

- **Web Components & Hooks (WEB-04.1 to WEB-04.6):**
  - WEB-04.1: `useIdempotencyKey` generates one key per action attempt, keeps key across network timeouts / 5xx / 409 `REQUEST_IN_PROGRESS`, and resets on 2xx or terminal 4xx (`useIdempotencyKey.test.ts`).
  - WEB-04.2: `idempotentPost` wrapper sets `Idempotency-Key` and `If-Match`, with 15s timeout and structured result handling.
  - WEB-04.3: `SubmitButton` disables double submits and shows "Confirming…" while pending (`SubmitButton.test.tsx`).
  - WEB-04.4: `IdempotentRetryPanel` displays customer and staff variants with shortened key (`c0a8…89ab`) and safe retry button (`IdempotentRetryPanel.test.tsx`).
  - WEB-04.5: `AppointmentModifiedAlert` displays changes in order and provides "Review and retry" without automatic resend (`AppointmentModifiedAlert.test.tsx`).
  - WEB-04.6: `diffAppointment` pure function computes ordered field differences between before and latest states (`diffAppointment.test.ts`).

- **BA Metrics (§B2):**
  - M-04.2 (appointments with incorrect active allocation counts): **0 rows** (threshold 0).
  - M-04.4 (active overlapping allocations): **0 rows** (threshold 0).
  - M-04.5 (stale in-progress idempotency keys older than 5 minutes): **0 rows** (threshold 0).

---

## 2. SOLID Architecture

- **Single Responsibility (SRP):**
  - `AppointmentWriter`: Focuses exclusively on transactional creation and rescheduling/relocation of appointments with pair fallback and rollback.
  - `AppointmentTransition`: Encapsulates valid status lifecycle progression and allocation freeing.
  - `AppointmentRepository`: Centralizes optimistic locking version checks and single-point updates.
  - `ResourceAllocationRepository`: Manages insertion and deactivation of GiST range allocations.
  - `OutboxWriter`: Handles transaction-guarded outbox event persistence and payload redaction.
  - `Idempotency` middleware: Manages deduplication, canonical request hashing, and replaying.
- **Open/Closed (OCP):**
  - `OutboxWriter` maps event channels through extensible lookup structures.
  - `AppointmentStatus` transitions defined through clean lookup matrix.
- **Liskov Substitution (LSP):**
  - All repository and service implementations faithfully implement their respective interfaces (`AppointmentWriterInterface`, `AppointmentTransitionInterface`, `AppointmentRepositoryInterface`, etc.).
- **Interface Segregation (ISP):**
  - Granular interfaces defined under domain boundaries without unused coupling.
- **Dependency Inversion (DIP):**
  - Controllers and services depend strictly on Domain interfaces bound via `DomainServiceProvider`. Eloquent models are confined to Repositories.

---

## 3. Security Review (OWASP Top 10)

- **A01:2021 — Broken Access Control:**
  - Idempotency keys are partitioned by `user_id` (`D-04.11`), preventing cross-user key collision or replay attacks.
  - Scoped dealership queries ensure tenants cannot view or manipulate foreign allocations.
  - Testing routes are strictly unregistered in production environments.
- **A02:2021 — Cryptographic Failures:**
  - Request hashing uses SHA-256 over normalized method, path, and sorted JSON payloads.
  - UUIDs generated via standard cryptographic generators (`Str::uuid()`, `crypto.randomUUID()`).
- **A03:2021 — Injection:**
  - Parameterized database operations; PostgreSQL range creation uses parameterized `tstzrange(?, ?, '[)')`.
- **A04:2021 — Insecure Design:**
  - Concurrency guarantees enforced at database engine level via PostgreSQL GiST EXCLUDE constraint rather than vulnerable application-level reads.
  - Optimistic locking (`ETag`/`If-Match`) prevents lost update anomalies.
- **A05:2021 — Security Misconfiguration:**
  - RFC 9457 Problem Details standard for all API error responses.
  - Sensitive internal exceptions and stack traces are suppressed in problem responses.
- **A06:2021 — Vulnerable and Outdated Components:**
  - Pinned framework and library versions. Zero additional untrusted dependencies.
- **A07:2021 — Identification and Authentication Failures:**
  - Sanctum session authentication enforced on mutating endpoints.
- **A08:2021 — Software and Data Integrity Failures:**
  - Outbox messages committed in the exact same transaction as the appointment write, guaranteeing zero orphaned or missing outbox rows.
- **A09:2021 — Security Logging and Monitoring Failures:**
  - Personal data (customer phone number, email address) is explicitly redacted from outbox payloads (`INV-11.5`) and event streams.
- **A10:2021 — Server-Side Request Forgery (SSRF):**
  - No outbound HTTP requests triggered in request path; external delivery deferred to background outbox relay.

---

## 4. Extensibility against Future Constraints (FC-04.1 to FC-04.4)

- **FC-04.1 (Phase 2 Advisory Locks):** Seam exists cleanly in `AppointmentWriter` transaction wrapper.
- **FC-04.2 (Database portability):** EXCLUDE constraint encapsulation is isolated inside `ResourceAllocationRepository` and migrations.
- **FC-04.3 (Partner API integration):** `Idempotency-Key` and `If-Match` headers follow standard HTTP specifications and RFC 9457 error contracts.
- **FC-04.4 (Additional notification channels):** `OutboxChannel` and `OutboxEventType` fan-out in `OutboxWriter` are straightforward to extend.

---

## 5. Verification & Quality Gates

- **Backend Tests:** 264 passing tests (2,131 assertions) including multi-process concurrency suites on real PostgreSQL.
- **Pint:** Formatted according to project standards; 0 style issues.
- **PHPStan:** Level 8 static analysis passing with 0 errors.
- **Frontend Vitest:** 21 test files passing (77 unit & component tests).
- **Frontend ESLint & TypeScript:** Passing with 0 errors.
