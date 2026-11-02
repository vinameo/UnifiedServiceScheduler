# F04 · No double booking, no duplicate requests · Requirement spec (RQ)

Branch: `feature/04-booking-integrity` · Priority: Must · Shared conventions: [README](../README.md) · Read first: SDD §5–§10, `CLAUDE.md` "The core rules"

---

# PART A · For readers

## A1. Story

**Why this feature exists.** The biggest risk of the system is **double booking**: two people take the last free slot at once, or two people change the same appointment from old screens and one change is lost. A customer tapping "Book" twice on a slow network must not get two bookings either.

**The problem.** "Check, then write" is not safe when many requests run at once on many PHP-FPM workers.

**What it does.** It builds three shared protections that every feature writing appointments uses:
1. **New bookings**: PostgreSQL refuses two active allocations that overlap for the same technician, bay or vehicle (EXCLUDE constraint). A technician/bay clash → try the next pair (at most 3 times), then "no availability"; a vehicle clash → "vehicle already booked" at once.
2. **Changes to an existing appointment**: each appointment has a version; every change must send the version it saw (`If-Match`). An old version → 412; the user reloads and decides.
3. **Repeated requests**: every writing request carries an `Idempotency-Key`. The same key again → the same answer as before, nothing written twice.

Plus: an appointment history table and an outbox table (messages written in the same transaction; F11 sends them).

**Who uses it.** The system. Users only see the error messages (the "Booking error states" draft; copy in F02).

**What it does not do.** The real booking endpoint (F02), cancel (F07), reschedule (F09)… F04 delivers the foundation and proves it through routes that exist only in the test environment.

## A2. Key rules in plain words

- The DB is the real guard. Checks in code exist only to give nice messages.
- An appointment and its 3 allocation rows (technician, bay, vehicle) are saved in **one** transaction: all or nothing.
- A status that frees the slot (cancelled, completed, no-show, hold expired) deactivates the allocations in the same transaction.
- The version goes up by 1 on **every** write, background jobs included. Never guess a version; never retry a 412 on the server.
- Keys are kept 24 hours. Same key + same body → the old answer. Same key + different body → error. Same key still running → "in progress".

## A3. Screens

No screen of its own. The messages users see (draft "Booking error states") are shown by F02 (New booking) and the change screens. F04 delivers shared web pieces: the idempotency-key hook, the "No response · safe retry" panel, the 412 alert ([5-web-spec.md](5-web-spec.md)).

## A4. Out of scope

Per-dealership-and-day lock (SDD phase 2), a queue (phase 3), SERIALIZABLE.

## A5. Success criteria

- 20 parallel requests for the last slot: exactly 1 succeeds, 19 are refused, the DB holds exactly 1 booking (AC-04.1).
- After every booking test, the overlap query returns 0 rows.

## A6. Decisions

| ID | Decision |
| --- | --- |
| D-04.1 | F04 is built before F02 (`CLAUDE.md`). F04 proves its ACs through routes registered only when `APP_ENV=testing` (`/api/v1/_testing/*`) that call the real seams. F02, F07, F09 prove them again through the real endpoints (named in their TCs). |
| D-04.2 | F04 creates the **full** `appointments` schema for F02–F12 (cancel, hold, contact required, reminders) so later features never alter the core table. Only the "one hold per customer" index belongs to F10. |
| D-04.3 | F04 creates `outbox_messages` and `OutboxWriter`; an appointment created as `CONFIRMED` writes `AppointmentConfirmed` from F02 on. The relay is F11. |
| D-04.4 | One outbox row per (event, channel); the rows of one event share an `event_id`. AC-11.1 "one row" is read as one event (one `event_id`). Receivers drop duplicates by `event_id` + channel. |
| D-04.5 | `appointment_events` table (history): every appointment write adds one row in the same transaction. F05 shows it as "History". |
| D-04.6 | Display `reference` = `APT-` + local date of the start time at creation (`YYYYMMDD`) + `-` + a number from a global sequence, padded to 4 digits (`APT-20261102-0042`). It never changes on reschedule. A sequence (no lock) keeps parallel requests fast. |
| D-04.7 | A DB trigger enforces `NEW.version = OLD.version + 1` on every `UPDATE` of `appointments` (the SDD §9 safety net). |
| D-04.8 | Status transitions (`AppointmentStatus::canMoveTo`): `HELD → CONFIRMED, CANCELLED, EXPIRED`; `CONFIRMED → CHECKED_IN, CANCELLED, NO_SHOW`; `CHECKED_IN → IN_PROGRESS`; `IN_PROGRESS → COMPLETED`; final statuses go nowhere. `CHECKED_IN` cannot be cancelled (the car is in the workshop). |
| D-04.9 | Every status change goes through one seam, `TransitionAppointment` (check `canMoveTo` → versioned write → deactivate allocations when the new status does not hold the slot → history row). |
| D-04.10 | Same key while the first request is **still running**: the later request gets `409 REQUEST_IN_PROGRESS` (FR-04.8). A request arriving **after** the first finished gets the saved answer. (SDD §13 says "all 10 get the same answer" — the FR is more specific and wins.) |
| D-04.11 | A key belongs to **one user**: unique `(user_id, key)`. The same key from another user is a new request of that user and never returns the first user's answer. |
| D-04.12 | Save the answer for every status < 500 (2xx and business 4xx, including 409, 412, 422). Status ≥ 500 → delete the key row so a retry really runs. |
| D-04.13 | An `IN_PROGRESS` key row has `locked_until = now + 60s`; once that passes (worker died), a later request takes it over with one atomic `UPDATE`. |
| D-04.14 | Body hash = SHA-256 of `METHOD \n path \n canonical JSON body (keys sorted recursively)`. `If-Match` is not part of it. A key reused on another endpoint → different hash → 422. |
| D-04.15 | `If-Match` accepts only the strong form `"N"` (integer N ≥ 1). Missing → 428; malformed (`*`, `W/"3"`, `abc`) → 400 `VALIDATION_ERROR`. |
| D-04.16 | After 23P01 on resource X, drop every remaining pair using X, then try the next pair. After 3 tries → `409 NO_AVAILABILITY` with `reason: "SLOT_TAKEN"`. |
| D-04.17 | A 23P01 error is mapped to a resource type by reading `resource_type` in PostgreSQL's error detail (`CLAUDE.md`). Unreadable → treat as `TECHNICIAN` (try the next pair) and log a warning. |
| D-04.18 | F04 dispatches the Laravel events `AllocationRetried`, `IdempotentReplayed`, `AppointmentModifiedDetected` for F08 to count; F04 does not depend on F08. |
| D-04.19 | F04 owns `AppointmentResource` (the JSON shape of an appointment) because 412 must return "the latest data". F02 reuses it for 201. |

## A7. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-04.a | Add a DB trigger that deactivates allocations when a status frees the slot? | No; seam D-04.9 + a test for every status + daily M-04.2. |
| Q-04.b | Include `If-Match` in the hash? | No (D-04.14). |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00, F01, F03 are merged: `CandidateFinder`, `AllocationSnapshotRepository`, `BusyIntervalReader` (bound to `NoBusyIntervals`), `World`, `SnapshotBuilder`. `ProblemException`, `ProblemRenderer`, `CorrelationId` exist from the start (`api/app/Support/Problem/`, `api/app/Http/Middleware/`). No appointment table exists yet.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-04.1 | Migrations: `appointments`, `resource_allocations` (EXCLUDE), `idempotency_keys`, `appointment_events`, `outbox_messages`, sequence `appointment_reference_seq`, version trigger (DB). |
| S-04.2 | Enums `AppointmentStatus` (`canMoveTo`, `holdsSlot`), `ResourceType`, `AppointmentEventType`, `OutboxEventType`, `OutboxChannel`. |
| S-04.3 | `AppointmentRepository` (`insert`, `find`, `updateVersioned`), `ResourceAllocationRepository` (`insertFor`, `deactivateFor`, `deactivateType`; implements `BusyIntervalReader`). |
| S-04.4 | `AppointmentWriter::create()` (3-try loop, 23P01 mapping), `TransitionAppointment`, `AppointmentEventRecorder`, `OutboxWriter`. |
| S-04.5 | Middleware `Idempotency`, `IfMatch`; `ExpectedVersion::from($request)`; daily `idempotency:prune` command. |
| S-04.6 | Exceptions: `NoAvailability` (409), `VehicleAlreadyBooked` (409), `AppointmentModified` (412), `InvalidStatusTransition` (422), `IdempotencyKeyRequired` (400), `IdempotencyKeyMismatch` (422), `RequestInProgress` (409), `PreconditionRequired` (428). |
| S-04.7 | `AppointmentResource` + `AppointmentResponder` (body + `ETag`). |
| S-04.8 | Test infrastructure: `TestingRoutesServiceProvider` (`/_testing/*` routes), `testing:request` command, `Parallel`, `AssertsNoOverlap`. |
| S-04.9 | Web: `useIdempotencyKey`, `IdempotentRetryPanel`, `AppointmentModifiedAlert` ([5-web-spec.md](5-web-spec.md)). |

## B3. Invariants

| ID | Invariant | Where/when it is checked |
| --- | --- | --- |
| INV-04.1 | For one `(resource_type, resource_id)`, there are never two **active** `resource_allocations` rows whose `period`s overlap. | The EXCLUDE constraint in the DB; no `SELECT` ever replaces it. Checked after every test by `AssertsNoOverlap`. |
| INV-04.2 | An appointment and its allocation rows are written in one `DB::transaction()`; on failure no row is left. | `AppointmentWriter`. Each try is a new transaction (after 23P01 the PostgreSQL transaction is broken; no savepoint to continue). |
| INV-04.3 | Every `UPDATE appointments` increments `version` by exactly 1. | The single seam `updateVersioned`; the DB trigger (D-04.7) refuses every other path. Never call Eloquent `save()`/`update()` on an appointment. |
| INV-04.4 | A change is saved only if the DB `version` equals `If-Match`: `UPDATE … WHERE id = ? AND version = ?`; 0 rows → `AppointmentModified` → rollback → 412 with the latest data. Never retry a 412 on the server. | `updateVersioned`; the latest data is read **after** the rollback (outside the transaction) when rendering. |
| INV-04.5 | A status that does not hold the slot (`COMPLETED`, `CANCELLED`, `NO_SHOW`, `EXPIRED`) → every active allocation of that appointment becomes `active = false` in the same transaction as the status change. | Seam `TransitionAppointment` (D-04.9). |
| INV-04.6 | An appointment in a slot-holding status has exactly one active allocation for each resource it needs (`TECHNICIAN`, `BAY`, and `VEHICLE` when it has a vehicle); one in a non-holding status has 0. `appointments.technician_id`, `bay_id` equal the `resource_id` of the matching active allocation. | Partial unique `(appointment_id, resource_type) WHERE active`; tests after each operation; M-04.2. |
| INV-04.7 | Order inside a change that moves the time: (1) versioned update (also locks the row), (2) deactivate old allocations, (3) insert new allocations, (4) write the outbox, (5) commit. A retry after 23P01 reruns the whole transaction with the **same** expected version. | Seam `AppointmentWriter::moveTo()` (used by F09, F12) — shape in B5. |
| INV-04.8 | The `Idempotency` middleware runs **before** `IfMatch`; both after `auth:sanctum`. | Order in the route group (TC-04-21). |
| INV-04.9 | For one `(user_id, key)`: at most one real run in 24 hours for each result < 500. | Unique `(user_id, key)` + `INSERT … ON CONFLICT DO NOTHING` run **outside** the business transaction (auto-commit) before the controller runs. |
| INV-04.10 | A replayed answer equals the first one: status, body, `Location`, `ETag`, `Content-Type` headers; plus `Idempotent-Replayed: true`. | Saved right after `$next()` returns the response (not in `terminate`, because it is needed before replying). |
| INV-04.11 | Every appointment write (create, status change, time change, reassign) adds exactly one `appointment_events` row in the same transaction, with the `version` after the change. | `AppointmentEventRecorder`, called from `AppointmentWriter` and `TransitionAppointment`. |
| INV-04.12 | An outbox row exists only if the business change committed (same transaction). No SMS/email/DMS call inside a request. | `OutboxWriter::append` only works inside a transaction (`DB::transactionLevel() > 0`, else `LogicException`). |
| INV-04.13 | Every time comparison (hold expiry, key lock expiry) uses the app's `now()` passed into SQL (README D-G.6). | Repositories take `CarbonImmutable $now`. |
| INV-04.14 | The `/_testing/*` routes and the `testing:request` command exist only when `APP_ENV=testing`. | The provider checks the environment in `register()`. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Create (INV-04.1, 04.2) | 20 parallel for 1 pair; 10 parallel for 2 pairs; vehicle clash; technician clash from a stale snapshot; forced failure mid-way; 3 tries used up | TC-04-01, 04-02, 04-08, 04-10, 04-09, 04-11 |
| Versioned change (INV-04.3, 04.4) | Two parallel changes on v3; old version; missing `If-Match`; malformed `If-Match`; direct UPDATE without increment | TC-04-06, 04-22, 04-07, 04-23, 04-13 |
| Freeing the slot (INV-04.5, 04.6) | Each of `CANCELLED`, `COMPLETED`, `NO_SHOW`, `EXPIRED`; wrong transition order | TC-04-14, TC-04-15 |
| Idempotency key (INV-04.9, 04.10) | Same key same body; different body; another endpoint; parallel; another user with the same key; first request 500; stale lock; older than 24 h | TC-04-03, 04-04, 04-24, 04-12, 04-16, 04-17, 04-18, 04-19 |
| Outbox (INV-04.12) | Commit; rollback; called outside a transaction | TC-04-20, TC-04-25 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/database/migrations/2026_11_05_*` | 5 tables + sequence + trigger ([3-database-spec.md](3-database-spec.md)). |
| `api/app/Domain/Booking/AppointmentStatus.php`, `ResourceType.php`, `AppointmentEventType.php` | Enums. |
| `api/app/Domain/Booking/Repositories/AppointmentRepository(.php, Interface.php)` | `insert`, `find`, `updateVersioned(string $id, int $expected, array $changes, CarbonImmutable $now): int` (returns the new version). |
| `api/app/Domain/Booking/Repositories/ResourceAllocationRepository(.php, Interface.php)` | `insertFor`, `deactivateFor`, `deactivateType`, `forDealership` (BusyIntervalReader). Replaces the `NoBusyIntervals` binding. |
| `api/app/Domain/Booking/AppointmentWriter.php` | `create(NewAppointment $data, list<CandidatePair> $pairs): Appointment`, `moveTo(…)` (B5). |
| `api/app/Domain/Booking/TransitionAppointment.php` | D-04.9. |
| `api/app/Domain/Booking/AppointmentEventRecorder.php` | INV-04.11. |
| `api/app/Domain/Booking/Exceptions/*.php` | S-04.6. Rendered through the existing `ProblemException`. |
| `api/app/Domain/Booking/Events/{AllocationRetried,IdempotentReplayed,AppointmentModifiedDetected}.php` | D-04.18. |
| `api/app/Domain/Notifications/OutboxWriter.php`, `OutboxEventType.php`, `OutboxChannel.php` | D-04.3, D-04.4. |
| `api/app/Http/Middleware/Idempotency.php`, `IfMatch.php`; `api/app/Http/ExpectedVersion.php` | Aliases `idempotent`, `if-match` in `bootstrap/app.php`. |
| `api/app/Domain/Booking/Repositories/IdempotencyKeyRepository.php` | Reads/writes `idempotency_keys`. |
| `api/routes/console.php` | `idempotency:prune` → `Schedule::command(...)->daily()`. |
| `api/app/Http/Resources/AppointmentResource.php`, `api/app/Http/AppointmentResponder.php` | D-04.19; [4-api-spec.md](4-api-spec.md) §B2. |
| `api/app/Providers/TestingRoutesServiceProvider.php`, `api/routes/testing.php`, `api/app/Console/Commands/TestingRequest.php` | INV-04.14. |
| `api/tests/Support/{AssertsNoOverlap,Concurrency/Parallel}.php` | README §7. |
| `web/src/shared/idempotency/*`, `web/src/shared/appointments/AppointmentModifiedAlert.tsx` | WEB. |

## B5. Contracts

HTTP and headers: [4-api-spec.md](4-api-spec.md). Tables: [3-database-spec.md](3-database-spec.md).

Seam shapes (_illustrative_):

```php
final class AppointmentWriter {
    /** INV-04.1, 04.2, D-04.16, D-04.17. @param list<CandidatePair> $pairs */
    public function create(NewAppointment $data, array $pairs, CarbonImmutable $now): Appointment; // throws NoAvailability, VehicleAlreadyBooked

    /** INV-04.7: F09 reschedule, F12 extend/reassign. */
    public function moveTo(string $appointmentId, int $expectedVersion, TimeRange $newRange,
                           array $pairs, AppointmentEventType $event, ?OutboxEventType $outbox,
                           CarbonImmutable $now): Appointment; // throws AppointmentModified, NoAvailability, VehicleAlreadyBooked
}

final class TransitionAppointment {
    /** D-04.8, D-04.9, INV-04.5. $changes: extra columns (cancel_reason, checked_in_at…) */
    public function __invoke(string $id, int $expectedVersion, AppointmentStatus $to, array $changes,
                             ?OutboxEventType $outbox, ?User $actor, CarbonImmutable $now): Appointment;
}
```

`NewAppointment` holds: `dealershipId`, `customerId`, `vehicleId?`, `serviceTypeId`, `range`, `status` (`CONFIRMED` or `HELD`), `expiresAt?`, `reminderOptIn`, `source` (`STAFF`/`CUSTOMER`), `createdByUserId`.

## B6. Seams

The F04 rows of README §6.2. Plus:

| Seam | Reused in |
| --- | --- |
| `Parallel::run(int $n, Closure $requestFor): list<Result>` | F02, F07, F09, F10 (concurrency tests) |
| Events D-04.18 | F08 |
| `AppointmentResponder::created()/ok()` | F02, F05, F07, F09, F10, F12 |

FCs from BA: FC-04.1 (per-dealership-and-day lock) → one line in `AppointmentWriter` before the try loop; FC-04.2 (another DB) → the rule is used only in the migration + `ResourceAllocationRepository`, and the tests describe the behaviour.

## B7. Algorithms

### `AppointmentWriter::create`
1. Take the pairs from F03 (the caller already checked opening hours, permissions, vehicle ownership).
2. `tries = 0`. While pairs remain and `tries < 3`:
   1. `tries++`. Take the first pair.
   2. Transaction: insert `appointments` (version 1, `reference` per D-04.6, `technician_id`, `bay_id` from the pair) → insert allocations `TECHNICIAN`, `BAY`, and `VEHICLE` when there is a vehicle, all with `period = tstzrange(start, end, '[)')` → history row (`CREATED` or `HELD`) → if `CONFIRMED`: `OutboxWriter::append(AppointmentConfirmed)` → commit → return the appointment.
   3. Catch `QueryException` with `getCode() === '23P01'`: read the resource type (D-04.17).
      - `VEHICLE` → throw `VehicleAlreadyBooked` (with the appointment holding the vehicle if found, B8). No more tries.
      - `TECHNICIAN`/`BAY` → drop every pair using that resource (D-04.16), dispatch `AllocationRetried`, loop.
   4. Any other error → rethrow (500).
3. Out of pairs or 3 tries used → `NoAvailability(reason: 'SLOT_TAKEN')`. (If F03 returned no pair at all, the caller throws `NoAvailability(reason: <F03 failure>)` without calling `create`.)

### `updateVersioned` (INV-04.3, 04.4)

`UPDATE appointments SET …, version = version + 1, updated_at = :now WHERE id = :id AND version = :expected` → 0 rows → `AppointmentModified($id)`. Returns `expected + 1`.

### `TransitionAppointment`
1. Read the appointment (no lock). Missing → `AppointmentNotFound` (F05 adds the scoped 404; F04 throws the plain one).
2. `current.canMoveTo(to)` false → `InvalidStatusTransition(current)` (422). Note: if the read `version` already differs from `expected`, return 412 first (see the note below).
3. Transaction: `updateVersioned(status = to, …changes)` → if `!to.holdsSlot()`: `deactivateFor(id)` → history row → outbox (if any) → commit.

412 vs 422 order: the read in step 1 may be stale. If step 3 updates 0 rows, it is 412 even if step 2 passed. If step 2 sees an invalid transition but the read `version` ≠ `expected` → 412 (the user is looking at old data; reloading will show the new status). TC-04-26.

### `Idempotency` middleware (INV-04.9, 04.10, D-04.10–04.14)
1. No `Idempotency-Key` header → 400 `IDEMPOTENCY_KEY_REQUIRED`. Not a UUID → 400 `VALIDATION_ERROR` (field `Idempotency-Key`).
2. `hash` per D-04.14.
3. `INSERT INTO idempotency_keys (…, status 'IN_PROGRESS', locked_until now+60s, expires_at now+24h) ON CONFLICT (user_id, key) DO NOTHING` (auto-commit).
4. Inserted → run `$next`. Response < 500 → `UPDATE … status 'COMPLETED', response_*`. ≥ 500 → `DELETE`. Return the response.
5. Not inserted → read the row:
   - `expires_at <= now` → delete the old row, back to step 3 (once).
   - Different `request_hash` → 422 `IDEMPOTENCY_KEY_MISMATCH`.
   - `COMPLETED` → return the saved response + `Idempotent-Replayed: true`; dispatch `IdempotentReplayed`.
   - `IN_PROGRESS`, `locked_until > now` → 409 `REQUEST_IN_PROGRESS`.
   - `IN_PROGRESS`, `locked_until <= now` → `UPDATE … SET locked_until = now+60s WHERE id = ? AND status = 'IN_PROGRESS' AND locked_until <= now`; 1 row → run as in step 4; 0 rows → 409.

### `IfMatch` middleware
Missing → 428 `PRECONDITION_REQUIRED`. Malformed (D-04.15) → 400. Valid → `$request->attributes->set('expectedVersion', N)`.

## B8. Error table

| Situation | HTTP | `code` | Extra members |
| --- | --- | --- | --- |
| Writing POST without `Idempotency-Key` | 400 | `IDEMPOTENCY_KEY_REQUIRED` | — |
| `Idempotency-Key` not a UUID; malformed `If-Match` | 400 | `VALIDATION_ERROR` | `errors` |
| Out of pairs / 3 tries used | 409 | `NO_AVAILABILITY` | `reason`, `requestedStart`, `requestedEnd` (F06 adds `alternatives`) |
| Vehicle clash | 409 | `VEHICLE_ALREADY_BOOKED` | `existingAppointment {id, reference, start, end}` only if the caller may see that appointment (same customer, or staff of the same dealership); otherwise omitted |
| Same key still running | 409 | `REQUEST_IN_PROGRESS` | — |
| Old version | 412 | `APPOINTMENT_MODIFIED` | `appointment` (latest, `AppointmentResource`) + latest `ETag` header |
| Wrong status order | 422 | `INVALID_STATUS_TRANSITION` | `currentStatus` |
| Same key, different body | 422 | `IDEMPOTENCY_KEY_MISMATCH` | — |
| Missing `If-Match` | 428 | `PRECONDITION_REQUIRED` | — |

## B9. Acceptance criteria

The `/_testing/*` routes are described in [4-api-spec.md](4-api-spec.md) §B5.

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-04.1 | Only 1 matching technician and 1 bay | 20 parallel processes book 09:00 for 20 different cars | Exactly 1 × 201 and 19 × 409; one booking in the DB | TC-04-01 (again through the real endpoint: TC-02-20) |
| AC-04.2 | 2 matching technicians and 2 bays | 10 parallel processes for the same time | Exactly 2 × 201; no technician or bay is double-booked | TC-04-02 |
| AC-04.3 | A booking was made with key K | Send the same request with K | 201 with the same id; booking count unchanged | TC-04-03 (again: TC-02-21) |
| AC-04.4 | K was used for vehicle v-001 | Send K with vehicle v-002 | 422 `IDEMPOTENCY_KEY_MISMATCH` | TC-04-04 |
| AC-04.5 | The first request with K got 409 | Send K again | The saved 409 is returned, nothing runs again | TC-04-05 |
| AC-04.6 | An appointment at version 3 | Two parallel changes, both `If-Match: "3"` | One 200, one 412; final version 4 | TC-04-06 (again with real cancel + reschedule: TC-09-12) |
| AC-04.7 | An appointment | Send a change without `If-Match` | 428, nothing saved | TC-04-07 |
| AC-04.8 (new) | The vehicle is booked 09:00–10:00 | Book the same vehicle at 09:30 with another bay and person | 409 `VEHICLE_ALREADY_BOOKED` at once, no other pair tried | TC-04-08 |
| AC-04.9 (new) | The vehicle allocation is forced to fail | Create a booking | No appointment, no technician or bay allocation left | TC-04-09 |
| AC-04.10 (new) | Stale snapshot: pair 1's technician was just taken | Create with 2 pairs | 201 with pair 2; one `AllocationRetried` | TC-04-10 |
| AC-04.11 (new) | 5 pairs, every technician already taken | Create | 409 `NO_AVAILABILITY`, `reason: SLOT_TAKEN`, exactly 3 tries | TC-04-11 |
| AC-04.12 (new) | — | 10 processes with the same key K and body | 1 booking; every response is 201 (same id) or 409 `REQUEST_IN_PROGRESS`; at least one 201 | TC-04-12 |
| AC-04.13 (new) | An appointment at version 1 | `UPDATE appointments SET status='CANCELLED'` without incrementing | The DB refuses it | TC-04-13 |
| AC-04.14 (new) | A slot-holding appointment | Move it to each freeing status | Every allocation `active = false`; a history row exists | TC-04-14 |
| AC-04.15 (new) | — | `canMoveTo` for every status pair | Matches table D-04.8 | TC-04-15 |
| AC-04.16 (new) | User A used key K | User B sends K | B's request runs as new and never sees A's answer | TC-04-16 |
| AC-04.17 (new) | The first request with K returned 500 | Send K again | It really runs again | TC-04-17 |
| AC-04.18 (new) | Row K is `IN_PROGRESS` with a past `locked_until` | Send K | Taken over and run; one booking | TC-04-18 |
| AC-04.19 (new) | A key older than 24 hours | `idempotency:prune`; send that key again | Row deleted; the request runs as new | TC-04-19 |
| AC-04.20 (new) | — | Create a `CONFIRMED` booking; create a booking that is rolled back | Exactly 1 `AppointmentConfirmed` `event_id` (3 channel rows); none for the rolled-back one | TC-04-20 |
| AC-04.21 (new) | An expired `HELD` appointment holds the only technician | Read the snapshot | That range is not busy | TC-04-27 |
| AC-04.22 (new) | An appointment at version 4 | Change with `If-Match: "3"` | 412 with `appointment.version = 4` and `ETag: "4"` | TC-04-22 |
| AC-04.23 (new) | `APP_ENV=local` | List routes | No `/_testing/*` | TC-04-28 |

## B10. Implementation order

1. Ticket 01 (prefactor): `AssertsNoOverlap`, `Parallel`, `testing:request`, empty `TestingRoutesServiceProvider`; no behaviour change.
2. Migrations + enums + repositories + trigger.
3. Tracer bullet: `AppointmentWriter::create` + route `_testing/appointments` + AC-04.1, then `/code-review`.
4. `updateVersioned`, `TransitionAppointment`, `IfMatch`, 412/428.
5. `Idempotency` middleware + prune.
6. Outbox writer, history, `moveTo`.
7. Web seams.
8. Acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `AssertsNoOverlap` runs in `tearDown` of every test that creates bookings.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-04.1 | `RefreshDatabase` wraps a transaction → child processes cannot see the data | Concurrency tests use `DatabaseTruncation` (`CLAUDE.md`) |
| R-04.2 | The 23P01 detail format changes between PostgreSQL versions | D-04.17 + TC-04-29 checks the parser against real errors from the running DB |
| R-04.3 | Child processes cannot freeze the clock | Concurrency tests use real future times (README §7) |
| R-04.4 | Large responses bloat `idempotency_keys` | Save bodies ≤ 64 KB; larger → do not save, delete the key, log a warning (not expected for this API) |
| R-04.5 | The middleware saves the answer after the transaction committed; a worker dying between the two leaves the key `IN_PROGRESS` | D-04.13: taken over after 60 seconds; the takeover reruns → could it create a second booking? No: the rerun hits EXCLUDE on the vehicle (same vehicle, same time) → 409 `VEHICLE_ALREADY_BOOKED`. Recorded as RB-04.2 |
