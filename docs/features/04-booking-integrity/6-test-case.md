# F04 · No double booking, no duplicate requests · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Concurrency is real.** "Many people at once" tests run N separate PHP processes that wait for the same moment and then send their request, on real PostgreSQL. No loop inside one process pretending to be parallel.
- **The DB is the guard**: tests insert straight into the DB to prove EXCLUDE and the trigger refuse bad rows, not just the code.
- **After every booking test**, a query that checks "no two active allocations overlap" must return 0 rows.
- F04 has no real endpoint yet (D-04.1): tests call the `/_testing/*` routes through the real middleware and seams. F02, F07, F09 rerun the main ACs on real endpoints.
- Shared web pieces: component tests.

## A2. Manual scenarios

No screen. QA checks the user-visible behaviour in F02 (manual "double tap" and "network loss" scenarios).

## A3. Test data

`World::saigonD7()` + `onlyTechnicians([...])` to build "only one pair". Each concurrency test uses a real future time (tomorrow if the dealership is open, else the next open day), 09:00 local.

## A4. Not tested

Real load of thousands of requests (out of scope; NFR-02 is measured in F02/F08). Moving to SDD phase 2.

## A5. Exit criteria

Every TC green; the concurrency TCs pass 3 runs in a row (no flakiness).

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-04.1, NFR-01 | TC-04-01 (+ TC-02-20) |
| AC-04.2, NFR-01 | TC-04-02 |
| AC-04.3 | TC-04-03 (+ TC-02-21) |
| AC-04.4 | TC-04-04 |
| AC-04.5 | TC-04-05 |
| AC-04.6 | TC-04-06 (+ TC-09-12) |
| AC-04.7 | TC-04-07 |
| AC-04.8 | TC-04-08 |
| AC-04.9, INV-04.2 | TC-04-09 |
| AC-04.10, D-04.16 | TC-04-10 |
| AC-04.11 | TC-04-11 |
| AC-04.12, D-04.10 | TC-04-12 |
| AC-04.13, INV-04.3, D-04.7 | TC-04-13 |
| AC-04.14, INV-04.5, INV-04.6 | TC-04-14 |
| AC-04.15, D-04.8 | TC-04-15 |
| AC-04.16, D-04.11 | TC-04-16 |
| AC-04.17, D-04.12 | TC-04-17 |
| AC-04.18, D-04.13 | TC-04-18 |
| AC-04.19 | TC-04-19 |
| AC-04.20, INV-04.12, D-04.4 | TC-04-20, TC-04-25 |
| AC-04.21, D-03.7 | TC-04-27 |
| AC-04.22, INV-04.4 | TC-04-22 |
| AC-04.23, INV-04.14 | TC-04-28 |
| INV-04.1 | TC-04-01, 04-02, 04-31 + `AssertsNoOverlap` after every test |
| INV-04.7 | TC-04-32 |
| INV-04.8 | TC-04-21 |
| INV-04.10 | TC-04-03 |
| INV-04.11 | TC-04-33 |
| INV-04.13 | TC-04-27 |
| D-04.6 | TC-04-34 |
| D-04.14 | TC-04-24 |
| D-04.15, API §B4 | TC-04-23 |
| D-04.17, R-04.2 | TC-04-29 |
| D-04.18 | TC-04-10, TC-04-03, TC-04-22 (events dispatched) |
| DB-04.1–04.5 CHECKs | TC-04-30 |
| 412 before 422 | TC-04-26 |
| WEB-04.1–04.6 | TC-04-35…TC-04-39 |

## B2. Test cases

Backend in `api/tests/Feature/Booking/Integrity/` unless noted. Tests marked "Concurrency" use `DatabaseTruncation` + `Parallel` (README §7); the others use `RefreshDatabase`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-04-01 | AC-04.1 | Concurrency | `World` with only An + K1 (OIL); 20 customers, 20 vehicles | 20 processes `POST /_testing/appointments` 09:00 at the same moment | Exactly 1 × 201, 19 × 409 `NO_AVAILABILITY`; 1 `appointments` row; overlap query 0 | EXCLUDE removed or replaced by a SELECT | `ParallelBookingTest::test_AC_04_1_only_one_of_twenty_parallel_bookings_wins` |
| TC-04-02 | AC-04.2 | Concurrency | 2 BASIC technicians, 2 QUICK bays; 10 vehicles | 10 processes, same time | Exactly 2 × 201; 2 different technicians, 2 different bays | Next pair not tried after 23P01 | `…::test_AC_04_2_two_pairs_give_exactly_two_bookings` |
| TC-04-03 | AC-04.3, INV-04.10 | Integration | `POST /_testing/appointments` with K → 201 | Send the identical request with K | 201, same `id`, `Location`, `ETag`; header `Idempotent-Replayed: true`; 1 booking; `IdempotentReplayed` dispatched once | Writer runs again | `IdempotencyTest::test_AC_04_3_same_key_same_body_replays` |
| TC-04-04 | AC-04.4 | Integration | K used with vehicle v1 | Send K with vehicle v2 | 422 `IDEMPOTENCY_KEY_MISMATCH`; no new booking | Hash not compared | `…::test_AC_04_4_same_key_different_body_is_422` |
| TC-04-05 | AC-04.5, D-04.12 | Integration | First request with K returns 409 (no slot) | Free the slot, send K again | Still the saved 409 (not 201); the writer does not run (`_testing/echo` counter or spy) | Only 2xx saved | `…::test_AC_04_5_saved_409_is_replayed` |
| TC-04-06 | AC-04.6 | Concurrency | Appointment at version 3 | 2 processes `POST /_testing/appointments/{id}/touch` with `If-Match: "3"`, different keys | One 200 (`ETag: "4"`), one 412; final `version` 4 | `UPDATE` without `AND version = ?` | `ParallelVersionTest::test_AC_04_6_two_changes_on_same_version_one_wins` |
| TC-04-07 | AC-04.7 | Integration | Appointment at version 1 | `touch` without `If-Match` | 428 `PRECONDITION_REQUIRED`; `version` still 1; no new history row | Version guessed | `IfMatchTest::test_AC_04_7_missing_if_match_is_428` |
| TC-04-08 | AC-04.8 | Integration | Vehicle v1 booked 09:00–10:00 (An, K1); Dũng + K2 free | Book v1 at 09:30 | 409 `VEHICLE_ALREADY_BOOKED` with `existingAppointment.id`; `AllocationRetried` dispatched 0 times | Vehicle clash treated like a person clash | `AppointmentWriterTest::test_AC_04_8_vehicle_clash_fails_at_once` |
| TC-04-09 | AC-04.9, INV-04.2 | Integration | The allocation repository is wrapped to throw on the `VEHICLE` insert | `AppointmentWriter::create` | Throws; 0 appointments; 0 allocations | Inserts outside a transaction | `…::test_AC_04_9_all_or_nothing` |
| TC-04-10 | AC-04.10, D-04.16 | Integration | Pairs (An,K1),(Dũng,K1)…; an An allocation 09:00–10:00 inserted by hand after the snapshot | `create` with the stale pairs | 201 with Dũng; 1 `AllocationRetried` | Stops at the first try | `…::test_AC_04_10_stale_pair_retries_next` |
| TC-04-11 | AC-04.11 | Integration | 5 pairs; every technician in them already has an overlapping allocation inserted by hand | `create` | 409, `reason: SLOT_TAKEN`; exactly 3 tries (`AllocationRetried` = 3) | More than 3 tries | `…::test_AC_04_11_three_tries_then_slot_taken` |
| TC-04-12 | AC-04.12, D-04.10 | Concurrency | — | 10 processes, same K, same body | 1 booking; every response is 201 with the same `id` or 409 `REQUEST_IN_PROGRESS`; ≥ 1 × 201 | Key inserted inside the business transaction | `ParallelIdempotencyTest::test_AC_04_12_same_key_in_parallel_books_once` |
| TC-04-13 | AC-04.13, D-04.7 | Integration | Appointment at version 1 | `DB::table('appointments')->update(['status' => 'CANCELLED'])` without changing the version; then `version = version + 2` | Both throw `QueryException` (P0001) | Trigger missing | `VersionTriggerTest::test_AC_04_13_update_without_increment_is_rejected` |
| TC-04-14 | AC-04.14, INV-04.5, INV-04.6 | Integration | One appointment per path: CONFIRMED→CANCELLED; HELD→EXPIRED; CONFIRMED→NO_SHOW; CONFIRMED→CHECKED_IN→IN_PROGRESS→COMPLETED | `TransitionAppointment` along the path | At the final status: 0 active allocations, `deactivated_at` set; 1 history row per step; M-04.2 returns 0 rows | One status forgets to deactivate | `TransitionTest::test_AC_04_14_freeing_statuses_release_allocations` (data provider) |
| TC-04-15 | AC-04.15, D-04.8 | Unit | — | `canMoveTo` for all 8×8 pairs | Matches D-04.8; `holdsSlot` matches `CONTEXT.md` | One cell wrong | `api/tests/Unit/Booking/AppointmentStatusTest::test_AC_04_15_transition_table` |
| TC-04-16 | AC-04.16, D-04.11 | Integration | User A used K → 201 | User B sends K with B's body | 201 with **B's** booking (different id) | Global keys | `IdempotencyTest::test_AC_04_16_keys_are_per_user` |
| TC-04-17 | AC-04.17 | Integration | `POST /_testing/echo?fail=500` with K | Send K without `fail` | The second request really runs (counter up), 200 | 500 saved | `…::test_AC_04_17_server_errors_are_not_saved` |
| TC-04-18 | AC-04.18, D-04.13 | Integration | Row K inserted by hand as `IN_PROGRESS`, `locked_until = now - 1s` | Send K | Runs, 200; row becomes `COMPLETED` | Stuck forever | `…::test_AC_04_18_stale_lock_is_taken_over` |
| TC-04-19 | AC-04.19 | Integration | Row K with `expires_at` one hour ago | `artisan idempotency:prune`; then send K | Row deleted; the request runs as new | No prune / expired key not ignored | `…::test_AC_04_19_expired_keys_are_pruned_and_ignored` |
| TC-04-20 | AC-04.20, D-04.4 | Integration | — | Create a `CONFIRMED` booking; then one forced to roll back | Booking 1: 3 outbox rows (SMS, EMAIL, DMS) with one `event_id`, `PENDING`, payload without phone/email; booking 2: 0 rows | Outbox written after commit | `OutboxWriterTest::test_AC_04_20_outbox_rows_commit_with_the_booking` |
| TC-04-21 | INV-04.8 | Integration | Appointment at version 2; key K already has a saved 200 from the previous change | Send K again with `If-Match: "1"` (old) | The saved 200, **not** 412 | `if-match` runs before `idempotent` | `MiddlewareOrderTest::test_INV_04_8_idempotency_runs_before_if_match` |
| TC-04-22 | AC-04.22, INV-04.4 | Integration | Appointment at version 4 | `touch` with `If-Match: "3"` | 412 `APPOINTMENT_MODIFIED`; body `appointment.version = 4`; header `ETag: "4"`; `AppointmentModifiedDetected` dispatched | 412 without the latest data | `IfMatchTest::test_AC_04_22_412_carries_latest_data` |
| TC-04-23 | D-04.15, API §B4 | Integration | Appointment at version 1 | `touch` with `If-Match`: `*`, `W/"1"`, `"0"`, `"-1"`, `"1.5"`, `abc`, `"999"`; `Idempotency-Key`: missing, `abc` | 400 ×6, 412 for `"999"`; 400 `IDEMPOTENCY_KEY_REQUIRED`, 400 `VALIDATION_ERROR` | Weak form or `*` accepted | `HeaderAdversarialInputTest::test_header_adversarial_input` (data provider) |
| TC-04-24 | D-04.14 | Integration | K used with body `{"a":1,"b":2}` at `/_testing/echo` | Send K with `{"b":2,"a":1}`; then K at `/_testing/appointments` | First: the saved answer; second: 422 MISMATCH | Hash depends on key order; path not included | `IdempotencyTest::test_D_04_14_hash_is_canonical_and_path_bound` |
| TC-04-25 | INV-04.12 | Unit | — | `OutboxWriter::append` outside a transaction | `LogicException` | Writes allowed outside a transaction | `api/tests/Unit/Notifications/OutboxWriterGuardTest` |
| TC-04-26 | 412/422 order | Integration | Appointment v3 `CONFIRMED`; someone else moved it to `CANCELLED` (v4) | `transition` to `CHECKED_IN` with `If-Match: "3"` | 412 (not 422) with `appointment.status = CANCELLED` | Status checked before version | `TransitionTest::test_stale_version_beats_invalid_transition` |
| TC-04-27 | AC-04.21, D-03.7, INV-04.13 | Integration | A `HELD` appointment with `expires_at = now - 1s` holds An 09–10; app clock frozen | `AllocationSnapshotRepository::forDay` | An not busy 09–10; a still-valid `HELD` is busy | SQL `now()` used, or no filter | `BusyIntervalReaderTest::test_AC_04_21_expired_holds_are_not_busy` |
| TC-04-28 | AC-04.23, INV-04.14 | Integration | Boot the app with `APP_ENV=local` (child process) | `php artisan route:list --json` | No route starts with `api/v1/_testing`; `testing:request` not in `list` | Provider registered unconditionally | `TestingRoutesTest::test_AC_04_23_testing_routes_only_in_testing` |
| TC-04-29 | D-04.17, R-04.2 | Integration | Cause a real 23P01 for each resource type | Parser reads the `QueryException` | Returns `TECHNICIAN`, `BAY`, `VEHICLE` correctly; an unknown string → `TECHNICIAN` + warning log | Parser tied to an old format | `ExclusionViolationParserTest` |
| TC-04-30 | DB-04.1–04.5 | Integration | — | Insert: `period` `(]`; empty `period`; `HELD` without `expires_at`; `CONFIRMED` without vehicle; `version = 0`; two active allocations of one type for one appointment | Each fails with `23514` or `23505` | Missing CHECK/unique | `SchemaChecksTest::test_schema_checks` (data provider) |
| TC-04-31 | INV-04.1 | Integration | Active BAY allocation K1 09:00–10:00 | Insert by hand K1 09:59–10:30 active; K1 10:00–11:00 active; K1 09:30 inactive | 23P01; ok; ok | `WHERE active` missing or closed ranges | `ExclusionConstraintTest::test_INV_04_1_db_rejects_active_overlap_only` |
| TC-04-32 | INV-04.7 | Integration | Appointment An+K2 10:00–11:00 v1 | `moveTo` 10:30–11:30 with pair (An,K2); a second time with the first pair taken and `expected` = 1 | First: 200, v2, no self-clash; second: tries the next pair with the same `expected` (no false 412) | Inserts before deactivating the old rows | `AppointmentWriterTest::test_INV_04_7_move_frees_old_before_taking_new` |
| TC-04-33 | INV-04.11 | Integration | — | Create, `touch`, `transition` | Each operation adds exactly 1 `appointment_events` row, `version` matches `appointments.version` | History forgotten | `AppointmentEventsTest::test_INV_04_11_one_event_per_write` |
| TC-04-34 | D-04.6 | Integration | Dealership at UTC+7 | Create 2 bookings: 2026-11-02 06:30 local (= 01-11 23:30Z) and 2026-11-03 | `reference` matches `^APT-20261102-\d{4,}$` and `^APT-20261103-\d{4,}$`; they differ | UTC date used | `ReferenceTest::test_D_04_6_reference_uses_local_date` |
| TC-04-35 | WEB-04.1 | Web unit | — | Call the hook with input A, A, then B, then `reset()` | Same key for A; new for B; new after reset | New key every render | `web/src/shared/idempotency/useIdempotencyKey.test.ts` |
| TC-04-36 | WEB-04.2, WEB-04.4 | Web component | Fake API: first call times out, second 201 | Submit; see the panel; tap "Retry" | Panel "No response from the server yet"; the second call sends the **same** `Idempotency-Key` | Retry makes a new key | `…/IdempotentRetryPanel.test.tsx` |
| TC-04-37 | WEB-04.3 | Web component | Fake API hangs | Click submit twice quickly | Exactly 1 request; the button shows "Confirming…" and is `disabled` | No button lock | `…/SubmitButton.test.tsx` |
| TC-04-38 | WEB-04.5, WEB-04.6 | Web component | `before` CONFIRMED 09:00 An; `latest` CANCELLED 09:00 An | Render the alert | "Status: Confirmed → Cancelled"; no Time/Technician rows; the button calls `onReview(latest, '"4"')`; no request sent | Automatic retry | `…/AppointmentModifiedAlert.test.tsx`, `diffAppointment.test.ts` |
| TC-04-39 | WEB §B2 | Web component | Fake API returns 409 `REQUEST_IN_PROGRESS` | Submit | `inProgress.body` copy; "Retry" uses the same key | Treated as a final error | `…/IdempotentRetryPanel.test.tsx` |

## B3. Fixtures

- `Tests\Support\AssertsNoOverlap`: `assertNoActiveOverlap()` runs the README §7 query; the trait calls it in `tearDown`.
- `Tests\Support\Concurrency\Parallel::run(int $n, callable $requestFor): array` — builds N commands `php artisan testing:request --user=… --method=POST --uri=… --json=… --header=… --start-at=<ms>`; each child sleeps until the start time, then sends; returns `[{status, headers, body}]`. Pass `APP_ENV=testing`, `DB_DATABASE=scheduler_testing` explicitly to children.
- `World::onlyTechnicians()`, `World::onlyBays()` (F01) to build "1 pair", "2 pairs".
- `Tests\Support\Booking\AppointmentFactory`: creates bookings + allocations through `AppointmentWriter` (never by hand) for tests that need existing bookings.

## B4. Regression

F00, F01, F03 tests; especially `CandidateFinderTest` (F04 changes the `BusyIntervalReader` binding; F03 unit tests do not change).
