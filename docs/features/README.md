# Feature specs · Unified Service Scheduler

Each feature F00–F12 has a folder `docs/features/<nn>-<slug>/` with 6 files (RQ, BA, DB, API, WEB, TC) written with the `write-feature-spec` skill. The folder name is also the branch name: `feature/<nn>-<slug>`.

This file holds the **shared conventions** so the 13 specs do not repeat each other: global decisions, the permission matrix, the error-code catalogue with copy, the table and seam registry, and the shared test fixtures. Feature specs point here by section number (for example "README §4").

Sources: [Feature Requirements](../Unified%20Service%20Scheduler%20·%20Feature%20Requirements.md) (FR/BR/AC), [SDD](../Unified%20Service%20Scheduler-System%20Design%20Document.md), [designs](../designs/README.md), `CLAUDE.md`, `CONTEXT.md` (glossary).

Code checked at: HEAD `0e39f86`, plus the uncommitted `api/`, `web/`, `docker/` in the working tree on 2026-11-02.

## 1. Features and build order

| # | Folder | Feature | Priority | Built after |
| --- | --- | --- | --- | --- |
| 1 | [00-accounts-and-sign-in](00-accounts-and-sign-in/) | F00 Accounts and sign-in | Must | — |
| 2 | [01-master-data](01-master-data/) | F01 Master data | Must | F00 |
| 3 | [03-slot-allocation](03-slot-allocation/) | F03 Free slots and resource allocation | Must | F01 |
| 4 | [04-booking-integrity](04-booking-integrity/) | F04 No double booking, no duplicate requests | Must | F03 |
| 5 | [02-create-booking](02-create-booking/) | F02 Create a booking | Must | F04 |
| 6 | [05-view-appointments](05-view-appointments/) | F05 View appointments | Must | F02 |
| 7 | [06-alternative-times](06-alternative-times/) | F06 Suggest other free times | Should | F05 |
| 8 | [07-cancel-appointment](07-cancel-appointment/) | F07 Cancel an appointment | Should | F06 |
| 9 | [08-monitoring](08-monitoring/) | F08 Monitoring | Should | F07 |
| 10 | [09-reschedule](09-reschedule/) | F09 Reschedule | Could | F08 |
| 11 | [10-temporary-hold](10-temporary-hold/) | F10 Temporary hold for self-booking | Could | F09 |
| 12 | [11-outbox-notifications](11-outbox-notifications/) | F11 Notifications through the outbox | Could | F10 |
| 13 | [12-day-of-service](12-day-of-service/) | F12 Day-of-service operations | Could | F11 |

The order follows `CLAUDE.md` ("How to work in this repo"). Each feature relies only on what earlier features delivered; what a later feature needs is recorded as an `FC-`.

## 2. ID scheme

IDs carry the feature number so they never clash across specs: `D-02.3`, `INV-04.2`, `TC-04-07`.

| Prefix | Meaning | Note |
| --- | --- | --- |
| `FR-`, `BR-`, `AC-`, `NFR-` | Taken as-is from the requirements document | `BR-` here is a **business rule**, as in the requirements |
| `AC-xx.n` with a new n | AC added by the spec | Marked "(new)" |
| `D-`, `Q-`, `S-`, `INV-`, `R-`, `E-`/`F-`, `ADR-`, `M-`, `FC-` | As in the skill | |
| `RB-` | **Business risk** | The skill calls it `BR`; renamed because `BR-` is already a business rule |
| `DB-`, `API-`, `WEB-` | Layer requirements | |
| `TC-xx-nn` | Test case | Only in `6-test-case.md` |
| `D-G.n` | Global decision | Section 3 below |

## 3. Global decisions

The user approved every open point with its default (2026-11-02). Any row can change during the per-feature review.

| ID | Decision | Reason |
| --- | --- | --- |
| D-G.1 | Code lives in `api/` (Laravel 13, PHP 8.4) and `web/` (React 19, Mantine 9, TanStack Query 5, React Router 8). `CLAUDE.md` uses the same paths. | Real repo |
| D-G.2 | Plural table names per `CLAUDE.md`: `appointments`, `resource_allocations`, `idempotency_keys`, `outbox_messages`, `service_types`, `bays`, `technicians`… (the SDD and requirements use singular names). | `CLAUDE.md` wins on code conventions |
| D-G.3 | `users.id` stays `bigint` (existing migration). Every business table uses UUIDs (`HasUuids`, time-ordered UUID v7). | Ids in URLs are not guessable; users table already exists |
| D-G.4 | All docs and UI copy are in **English** (`CLAUDE.md`: "Copy is English"). Each web area keeps its copy in one `copy.ts` file so another language can be added later. | `CLAUDE.md` + user preference |
| D-G.5 | Format errors (missing field, wrong type, wrong format) are `400 VALIDATION_ERROR` with per-field `errors` (existing renderer). Correct format but broken rule is `422` with its own code. | Requirements "Shared rules" + existing `ProblemRenderer` |
| D-G.6 | Every business time comparison (in the past, hold expired, 2 hours before start) uses the app clock (`now()` from Carbon) passed into SQL as a parameter, never SQL `now()`. Exception: `created_at`/`updated_at`. | Tests can freeze time; one source of time |
| D-G.7 | API times: ISO 8601 with offset. Output uses the offset of the dealership's time zone (for example `+07:00`). The DB stores `timestamptz`. A `date=` parameter is the dealership's local date. | `CLAUDE.md` §Time |
| D-G.8 | Auth endpoints (`sign-up`, `sign-in`, `sign-out`) do **not** need `Idempotency-Key`. Every other `POST` that writes does. | A duplicate sign-up is already stopped by the unique email (409); a replayed response cannot set the session cookie again |
| D-G.9 | Customers and vehicles do not belong to a dealership (a customer can book at several). Appointments and every operational table have `dealership_id`. | NFR-03 applies to operational tables |
| D-G.10 | Backend tests run on real PostgreSQL, database `scheduler_testing` (`docker/postgres/init`). Concurrency tests run N separate PHP processes with `Process::pool()` through the `testing:request` command (README §7) and use `DatabaseTruncation`. | `CLAUDE.md` §Testing rules |
| D-G.11 | No new Composer or npm package unless a spec names it in a `D` row. Today only F08 adds packages (OpenTelemetry). | `CLAUDE.md`: ask before adding a package |
| D-G.12 | Lists page with a cursor: `limit` default 50, max 200; `nextCursor` is opaque (base64 of the sort key). A list whose screen shows a count also returns `meta.total` (the real count, not the page length). | FR-05.4 |
| D-G.13 | Business operations live in **services** per `CLAUDE.md`: an interface + a class in `app/Domain/<Module>/Services/`, bound in `DomainServiceProvider::$bindings`; controllers call only a module service interface. Operation names used in the specs (`CreateBooking`, `CancelAppointment`…) are methods of those services (map in §6.3). Pure rule classes without I/O (`BookingTimeRules`, `CustomerChangePolicy`, everything in `App\Domain\Allocation`) stay plain classes. | `CLAUDE.md` "Backend conventions" wins |
| D-G.14 | Gates and Policies are registered in `AppServiceProvider::boot()` (already in `bootstrap/providers.php`); there is no `AuthServiceProvider` in Laravel 13. | Real repo |
| D-G.16 | **Technician model** (user decision, 2026-11-03): each technician has one **level** 1–7 (trade grade); each service type requires one level; a technician qualifies when active and `level ≥ required level`. Skills, shifts, attendance and leave (absences) are out of scope: an active technician works whenever the dealership is open. Admins disable/enable technicians in Master data; disabling is refused while the technician has upcoming appointments. Bay outages are out of scope too. This replaces the skill and shift rules of FR-01, FR-03.1–03.4 and the absence/outage part of FR-12.3–12.4. | User decision |
| D-G.15 | An `Idempotency-Key` belongs to one **attempt** of an action: the web reuses it only to resend after no response, a 5xx, or 409 `REQUEST_IN_PROGRESS`. Any other answer (2xx, 4xx) is final for that key; trying again after it needs a new key. | The server saves every answer < 500 (F04 D-04.12), so reusing a key after a 412/409/422 replays the old answer |

## 4. Permission matrix

`✓` = allowed; `own` = own data only; `—` = 403 `FORBIDDEN`. Staff only see their own dealership's data; another dealership returns 404 `DEALERSHIP_NOT_FOUND`, an appointment they may not see returns 404 `APPOINTMENT_NOT_FOUND`.

| Action | Customer | Service advisor | Workshop foreman | Admin | Feature |
| --- | --- | --- | --- | --- | --- |
| Read dealerships, service types, free times | ✓ (any dealership) | ✓ (own dealership) | ✓ | ✓ | F01, F06 |
| Read bays, technicians | — | ✓ | ✓ | ✓ | F01 |
| Master data screen | — | — | — | ✓ | F01 |
| Disable / enable a technician | — | — | — | ✓ | F01 |
| Search customers, read a customer's vehicles | — | ✓ | — | — | F02 |
| Add a vehicle | own | — | — | — | F02 |
| Create a booking (`CONFIRM`) | own | ✓ | — | — | F02 |
| Hold (`HOLD`), confirm a hold | own | — | — | — | F10 |
| Read one appointment | own | ✓ | ✓ | ✓ | F05 |
| Day list of appointments | — | ✓ | ✓ | ✓ | F05 |
| My bookings | own | — | — | — | F05 |
| Cancel | own (≥ 2 h before) | ✓ | — | — | F07 |
| Reschedule | own (≥ 2 h before) | ✓ | — | — | F09 |
| Check in, no-show | — | ✓ | ✓ | — | F12 |
| Start, complete, extend | — | — | ✓ | — | F12 |
| Workshop board | — | ✓ | ✓ | ✓ | F12 |
| Outbox (read, retry) | — | — | — | ✓ | F11 |
| System monitoring | — | — | — | ✓ | F08 |

Seam: one Gate per ability (F00, README §6). No `if ($user->role === …)` in controllers.

## 5. Error codes and copy

Every error is `application/problem+json` (`type`, `title`, `status`, `code`, `detail`, `correlationId`, plus extra members). Clients act on `code` only. The web shows the "UI copy" column for each `code`, never the server's `detail` unless a spec says so.

| HTTP | `code` | Owner | UI copy | Extra members |
| --- | --- | --- | --- | --- |
| 400 | `VALIDATION_ERROR` | existing | Check the highlighted fields. | `errors{field: [msg]}` |
| 400 | `IDEMPOTENCY_KEY_REQUIRED` | F04 | Something went wrong with this request. Please try again. | — |
| 401 | `UNAUTHENTICATED` | existing | Please sign in to continue. | — |
| 401 | `INVALID_CREDENTIALS` | F00 | Email or password is incorrect. | — |
| 403 | `FORBIDDEN` | existing | You don't have access to this page. | — |
| 404 | `NOT_FOUND` | existing | This page does not exist. | — |
| 404 | `DEALERSHIP_NOT_FOUND` | F01 | Dealership not found. | — |
| 404 | `SERVICE_TYPE_NOT_FOUND` | F02 | This service is no longer available. | — |
| 404 | `CUSTOMER_NOT_FOUND` | F02 | Customer not found. | — |
| 404 | `VEHICLE_NOT_FOUND` | F02 | Vehicle not found. | — |
| 404 | `APPOINTMENT_NOT_FOUND` | F05 | We couldn't find this appointment. | — |
| 404 | `TECHNICIAN_NOT_FOUND` | F01 | Technician not found. | — |
| 404 | `OUTBOX_MESSAGE_NOT_FOUND` | F11 | Message not found. | — |
| 409 | `EMAIL_ALREADY_USED` | F00 | An account with this email already exists. Sign in instead. | — |
| 409 | `TECHNICIAN_HAS_BOOKINGS` | F01 | {name} still has {count} upcoming appointments. Move or cancel them before disabling. | `count`, `appointments[]` (≤ 20) |
| 409 | `PLATE_ALREADY_REGISTERED` | F02 | This licence plate is already registered. Call the dealership if it is your car. | — |
| 409 | `VIN_ALREADY_REGISTERED` | F02 | This VIN is already registered. | — |
| 409 | `NO_AVAILABILITY` | F04 (+F06 alternatives) | Heading per `reason` (F03 WEB §B2), for example "The {time} slot was just taken." | `reason`, `alternatives[]` |
| 409 | `VEHICLE_ALREADY_BOOKED` | F04 | Vehicle {plate} already has a booking at {range} that day. | `existingAppointment?` |
| 409 | `REQUEST_IN_PROGRESS` | F04 | Your request is still being processed. Wait a moment, then check again. | — |
| 410 | `HOLD_EXPIRED` | F10 | Your hold has expired. | — |
| 412 | `APPOINTMENT_MODIFIED` | F04 | This appointment was changed by someone else. Review the latest details and try again. | `appointment` (latest) + `ETag` header |
| 422 | `WEAK_PASSWORD` | F00 | Use at least 8 characters with at least one letter and one number. | — |
| 422 | `START_IN_PAST` | F02 | This time has already passed. Pick another time. | — |
| 422 | `START_TOO_FAR_AHEAD` | F02 | You can book up to 90 days ahead. | `maxDate` |
| 422 | `OUTSIDE_OPENING_HOURS` | F02 | The service would end after closing time. | `opensAt`, `closesAt`, `latestStart` |
| 422 | `VEHICLE_NOT_OWNED_BY_CUSTOMER` | F02 | This vehicle does not belong to {customer}. Choose another vehicle or change the customer. | — |
| 422 | `VEHICLE_LIMIT_REACHED` | F02 | You can save up to 10 vehicles. | — |
| 422 | `INVALID_STATUS_TRANSITION` | F04 | This appointment is {status} and can no longer be changed this way. | `currentStatus` |
| 422 | `IDEMPOTENCY_KEY_MISMATCH` | F04 | Something went wrong with this request. Please try again. | — |
| 422 | `TOO_LATE_TO_CANCEL` | F07 | It's less than 2 hours before your appointment. Please call {phone} to cancel. | `dealershipPhone` |
| 422 | `TOO_LATE_TO_RESCHEDULE` | F09 | It's less than 2 hours before your appointment. Please call {phone} to change it. | `dealershipPhone` |
| 422 | `RESCHEDULE_SAME_TIME` | F09 | Pick a time different from the current one. | — |
| 422 | `HOLD_NOT_ALLOWED` | F10 | Only customers can hold a time. | — |
| 422 | `NO_SHOW_BEFORE_START` | F12 | You can mark a no-show only after the start time. | — |
| 422 | `NOT_SERVICE_DAY` | F12 | You can check in only on the day of the appointment. | — |
| 422 | `NEEDS_RESCHEDULE` | F12 | This booking no longer has a bay or technician. Reschedule it before checking in. | — |
| 422 | `OUTBOX_NOT_RETRYABLE` | F11 | Only failed messages can be sent again. | — |
| 428 | `PRECONDITION_REQUIRED` | F04 | Something went wrong with this request. Please reload the page. | — |
| 429 | `TOO_MANY_ATTEMPTS` | F00 | Too many attempts. Try again in {minutes} minutes. | `Retry-After` header |
| 419 | `CSRF_TOKEN_MISMATCH` | existing | Your session has expired. Reload the page. | — |
| 500 | `INTERNAL_ERROR` | existing | Something went wrong on our side. Try again in a moment. | — |

Codes the spec adds beyond the requirements: `PLATE_ALREADY_REGISTERED`, `VIN_ALREADY_REGISTERED`, `VEHICLE_LIMIT_REACHED`, `CUSTOMER_NOT_FOUND`, `START_TOO_FAR_AHEAD`, `TOO_LATE_TO_RESCHEDULE`, `RESCHEDULE_SAME_TIME`, `HOLD_NOT_ALLOWED`, `NO_SHOW_BEFORE_START`, `NOT_SERVICE_DAY`, `NEEDS_RESCHEDULE`, `TECHNICIAN_NOT_FOUND`, `TECHNICIAN_HAS_BOOKINGS`, `OUTBOX_MESSAGE_NOT_FOUND`, `OUTBOX_NOT_RETRYABLE`. Each has a `D` row in its owning feature.

Web seam: `web/src/shared/api/errorCopy.ts` (F00) maps `code` → copy; an unknown code falls back to the `INTERNAL_ERROR` copy.

## 6. Shared tables and seams

### 6.1 Which feature creates which table

| Table | Created in | Note |
| --- | --- | --- |
| `users` (adds `role`, `dealership_id`), `customers`, `dealerships` | F00 | `dealerships` is created in F00 because staff accounts need one |
| `dealership_opening_hours`, `service_types`, `bays`, `technicians`, `vehicles` | F01 | No skill or shift tables (D-G.16) |
| `appointments`, `resource_allocations`, `idempotency_keys`, `appointment_events`, `outbox_messages` | F04 | Schema has every column F07–F12 need ("schema ready") |
| `api_events`, `metric_counters` | F08 | |

### 6.2 Which feature builds each seam, and who reuses it

| Seam | Built in | Used in |
| --- | --- | --- |
| `App\Domain\Auth\Role` (enum) + Gates in `AppServiceProvider::boot()` (D-G.14) | F00 | all |
| Route binding `{dealership}` that only returns the staff member's dealership (404 `DEALERSHIP_NOT_FOUND`) | F01 | F05, F06, F12 |
| `UpcomingAssignmentsReader` (a technician's upcoming appointments, used to refuse disabling): F01 binds `NoUpcomingAssignments`, F04 binds the real reader over `resource_allocations` | F01 | F04 |
| Technician row lock: disabling takes `FOR UPDATE`; every allocation write takes `FOR SHARE` and treats an inactive technician like a taken one | F01 (disable), F04 (writers) | F02, F09, F10, F12 |
| `App\Domain\Allocation\CandidateFinder`, `AllocationStrategy`, `ResourceSnapshot`, `OpeningHours` | F03 | F02, F06, F09, F10, F12 |
| `App\Domain\Booking\Repositories\AllocationSnapshotRepository` (one day's snapshot) | F03 | F02, F06, F09, F12 |
| `Idempotency` middleware (alias `idempotent`) | F04 | every writing POST |
| `IfMatch` middleware (alias `if-match`) + `ExpectedVersion::from($request)` | F04 | F07, F09, F10, F12 |
| `AppointmentRepository::updateVersioned()` (the only way to change an appointment) | F04 | F07, F09, F10, F12 |
| `ResourceAllocationRepository::insertFor()`, `deactivateFor()` | F04 | F02, F07, F09, F10, F12 |
| `AppointmentWriter::create()` (3 pair attempts, maps 23P01) | F04 | F02, F10 |
| `AppointmentWriter::moveTo()` (time change in the `CLAUDE.md` order) | F04 | F09, F12 |
| `TransitionAppointment` (status change + free allocations + history) | F04 | F07, F10, F12 |
| `AppointmentStatus::canMoveTo()` | F04 | F07, F10, F12 |
| `AppointmentEventRecorder` (history rows) | F04 | every change |
| `OutboxWriter` (outbox row in the same transaction) | F04 | F02, F07, F09, F10, F12; relay in F11 |
| `AppointmentResource` + `AppointmentResponder` (body + `ETag` + `Location`) | F04 | F02, F05, F07, F09, F10, F12 |
| `BookingTimeRules` (past, opening hours, 15-minute step, 90 days) | F02 | F06, F09 |
| `AppointmentRepository::findVisibleTo(User, id)` + explicit route binding `{appointment}` that calls it (404 when not visible); the only way a route loads an appointment | F05 | F05, F07, F09, F10, F12 |
| `AlternativeTimesFinder` | F06 | F02, F09 (409) |
| Web: `useIdempotencyKey(actionInputs)` (D-G.15), `SubmitButton`, `IdempotentRetryPanel`, `AppointmentModifiedAlert` | F04 | every writing mutation |
| Web: `appointmentQuery` keeps the `ETag` + `withIfMatch()` | F05 | F07, F09, F10, F12 |
| Web: `formatInZone(iso, timeZone)` | F02 | all |
| Web: `RequireRole` route guard, `useMe()` | F00 | all |
| Web: `SlotGrid` (free times, struck-through when taken) | F06 | F09, F10 |
| Web: `AppointmentStatusBadge` | F02 | F05, F12 |

### 6.3 Service map (D-G.13)

Each row is an interface + class pair in the folder shown, bound in `DomainServiceProvider`. "Called by controllers" services are the only ones a controller may type-hint.

| Interface (folder) | Methods (operation name used in the specs) | Built in | Called by controllers |
| --- | --- | --- | --- |
| `Auth/Services/SignUpServiceInterface`, `Auth/Services/SignInServiceInterface` | `signUp`; `signIn` (sign-out is a thin session call) | F00 | yes |
| `Customers/Services/CustomerServiceInterface` | `search` (`CustomerSearch`), `vehiclesOf`, `addVehicle` (`AddVehicle`) | F02 | yes |
| `Booking/Services/BookingServiceInterface` | `create` (`CreateBooking`), `cancel` (`CancelAppointment`, F07), `reschedule` (`RescheduleAppointment`, F09), `confirmHold` (`ConfirmHold`, F10) | F02 | yes |
| `Booking/Services/AppointmentQueryServiceInterface` | `show`, `history`, `listForDealershipDay`, `listForCustomer` | F05 | yes |
| `Booking/Services/AvailabilityServiceInterface` | `forDay` (`AvailabilityCalculator`), `near`/`next` (`AlternativeTimesFinder`) | F06 | yes |
| `Workshop/Services/WorkshopServiceInterface` | `checkIn`, `start`, `complete`, `markNoShow`, `extend` (`ExtendAppointment`), `board` | F12 | yes |
| `MasterData/Services/TechnicianServiceInterface` | `setActive` (disable / enable, D-G.16) | F01 | yes |
| `Notifications/Services/OutboxServiceInterface` | `list`, `summary`, `retry` (`RetryOutboxMessage`) | F11 | yes |
| `Booking/Services/AppointmentWriterInterface` | `create`, `moveTo`, `displace` (F12) | F04 | no |
| `Booking/Services/AppointmentTransitionInterface` | `transition` (`TransitionAppointment`) | F04 | no |
| `Booking/Services/AppointmentEventRecorderInterface`, `Notifications/Services/OutboxWriterInterface` | `record`, `append` | F04 | no |
| `Booking/Services/ExpiredHoldReleaserInterface` | `releaseBlocking` | F10 | no |
| `Workshop/Services/ReassignerInterface` | `reassign` | F12 | no |
| `Notifications/Services/OutboxRelayInterface`, `Notifications/Services/ReminderQueueInterface` | `runOnce` (`OutboxRelay`), `queue` (`QueueReminders`) | F11 | no (console commands) |

Code sketches in the feature specs show the operation as one method of these interfaces.

## 7. Shared fixtures and test infrastructure

| Fixture | Built in | Content |
| --- | --- | --- |
| `Database\Factories\*` for users, customers, dealerships | F00 | `User::factory()->advisor($dealership)`, `->customer()` |
| `Tests\Support\World` | F01 | Builds one standard dealership like the seed (opening hours, 3 service types, 4 bays, 5 technicians with levels) and returns the ids; tests adjust from there |
| `Tests\Support\Allocation\SnapshotBuilder` | F03 | Builds a `ResourceSnapshot` in memory for unit tests, no DB |
| `Tests\Support\AssertsNoOverlap` | F04 | Trait: one query that returns 0 rows when no two active allocations overlap; called in `tearDown` of every booking test |
| Command `testing:request` + `Tests\Support\Concurrency\Parallel` | F04 | Runs N PHP processes; each waits for the same start time, then sends one in-process HTTP request (through the real middleware and controller) as a given user; prints JSON `{status, body, headers}` |
| `Tests\Support\FreezesClock` | F02 | `Carbon::setTestNow` for single-process tests. Concurrency tests **cannot** freeze the clock of child processes: use real future times only |
| Web: `renderWithProviders`, a hand-written axios mock (no new package) | `web/src/test/render.tsx` exists; mock in F00 | |

The overlap check used by `AssertsNoOverlap` (illustrative):

```sql
SELECT a.id, b.id
FROM resource_allocations a
JOIN resource_allocations b
  ON a.resource_type = b.resource_type AND a.resource_id = b.resource_id
 AND a.id < b.id AND a.active AND b.active AND a.period && b.period;
```

Name tests after the requirement they prove: `test_AC_04_1_only_one_of_twenty_parallel_bookings_wins`, `test_INV_04_3_version_trigger_rejects_a_write_without_increment`.

## 8. Checks (shared Definition of Done)

```bash
docker compose exec php php artisan test
docker compose exec php ./vendor/bin/pint --test
docker compose exec php ./vendor/bin/phpstan analyse
cd web && npm run lint && npm run test
```

A feature is done when every `TC-` row is green (or run by hand with the result recorded), the commands above are green, and no existing test was weakened.
