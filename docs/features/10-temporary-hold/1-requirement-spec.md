# F10 · Temporary hold for self-booking · Requirement spec (RQ)

Branch: `feature/10-temporary-hold` · Priority: Could · Shared conventions: [README](../README.md) · Read first: SDD §8 (hold), §9 (expiry job), §12 "Hold expiry"

---

# PART A · For readers

## A1. Story

**Why this feature exists.** A customer booking on a phone needs a minute or two to pick a vehicle and check details. Without a hold, someone else can take the time in the meantime and the customer loses it at the last step.

**What it does.**
- On "Hold and continue", the time the customer picked is held for them for 5 minutes (`HOLD_MINUTES`). The hold blocks the technician and bay exactly like a real booking, so nobody else can take them.
- A countdown shows how long is left. The customer picks or adds a vehicle and confirms; the hold becomes a confirmed booking.
- If the 5 minutes pass, confirming returns "Your hold has expired" and the customer picks a time again (the details they entered are kept).
- A background job runs every 30 seconds and ends expired holds, freeing their slot. Any booking flow that meets an expired hold on its way also ends it, so a late job never blocks a slot for long.
- A customer has at most one hold at a time: a new hold ends the old one.

**Who uses it.** Signed-in customers in the app. Staff do not hold; they book directly.

**What it does not do.** Holds for staff, extending a hold, holds longer than 5 minutes, holding several slots.

## A2. Usage steps

1. Customer → Book a service → choose a service → choose date and time → "Hold and continue" (draft `Customer/screenshots/02-choose-date-time.png`).
2. Step 3 "Temporary hold" shows "Slot held for you · 04:32", the selection, name and phone, a vehicle picker and "Text me a reminder 1 day before" (draft `03-temporary-hold.png`).
3. "Confirm appointment" → "Booking confirmed".
4. If the countdown reaches 00:00 → "Your hold has expired" → "Pick a time again".

## A3. Screens and deviations from the drafts

| Draft | Spec | Reason |
| --- | --- | --- |
| Step 3 inputs for full name and phone | Read-only from the account (as in F02) | FR-10.2 |
| Step 3 "Licence plate" input only | Saved-vehicle picker + "Add a new vehicle" (as in F02) | FR-10.2 |
| F02's step 2 "Continue" and step 3 "Contact details" | Restored to the draft: "Hold and continue", "Slot held for you", countdown | FR-02.6 |

## A4. Key rules in plain words

- A hold lasts 5 minutes from when it is made, by the server's clock.
- While held, the slot is taken for everyone else (it disappears from their free-time grid).
- Confirming after the hold ended is refused; the customer picks a time again.
- The job and a confirm can never both win: the booking ends either confirmed or expired.
- One hold per customer; making a new one ends the previous one.
- The vehicle can be chosen after holding; it is checked when confirming.

## A5. Out of scope

Staff holds, longer or extendable holds, a waiting list.

## A6. Success criteria

- An abandoned hold frees its slot within at most 5 minutes + 30 seconds (job interval), or immediately when another booking needs it (FR-10.5).
- Confirm vs expiry race: always exactly one winner (AC-10.3, 100 runs).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-10.1 | `POST /appointments` accepts `mode: HOLD` for customers only; staff sending `HOLD` → 422 `HOLD_NOT_ALLOWED` (new code). `vehicleId` is optional for `HOLD`. |
| D-10.2 | A hold without a vehicle holds only `TECHNICIAN` and `BAY` (`appointments.vehicle_id` NULL, allowed only while `HELD`, F04 CHECK). With a vehicle it also holds `VEHICLE`. Confirm inserts (or swaps) the `VEHICLE` allocation; a clash → 409 `VEHICLE_ALREADY_BOOKED` and the hold stays. This deviates from SDD "3 rows per booking" for holds only. |
| D-10.3 | `expires_at = now + HOLD_MINUTES` (env, default 5) from the app clock. Confirm sets `expires_at = NULL`. |
| D-10.4 | One hold per customer: a partial unique index `(customer_id) WHERE status = 'HELD'` (migration in F10). Creating a hold first expires the customer's current hold **in the same transaction**; if the new hold fails (409), the old hold is kept. Holds of one customer are serialised: the hold transaction starts with `SELECT … FROM customers WHERE id = ? FOR UPDATE`, so a parallel second hold waits, then sees the first one and ends it like any current hold (both get 201; the later one wins). The unique index is the safety net: a `23505` there is a bug (500), never a business answer. |
| D-10.5 | `POST /appointments/{id}/confirm` with body `{ vehicleId, reminderOptIn }`, headers `If-Match`, `Idempotency-Key`. Customer (own hold) only. |
| D-10.6 | Confirm on a hold whose `expires_at <= now` → expire it (versioned) and return 410 `HOLD_EXPIRED`. |
| D-10.7 | Confirm that loses a race with the job gets 412 `APPOINTMENT_MODIFIED` whose `appointment.status` is `EXPIRED` (AC-10.3 "the loser gets 412"); the web treats it like 410. |
| D-10.8 | Job `holds:expire [--now=<ISO>]` (`--now` accepted only when `APP_ENV=testing`, so race tests can give the job a later clock than the confirm), `everyThirtySeconds()->withoutOverlapping()`, takes up to 500 expired holds ordered by `expires_at`, and moves each to `EXPIRED` through `TransitionAppointment` with its read version (`WHERE status = 'HELD' AND version = :v`); a 412 means "the customer just confirmed" → skip. One transaction per hold. |
| D-10.9 | Seam `ExpiredHoldReleaser::releaseBlocking(dealershipId, range, vehicleId?, now)`: expires holds with `expires_at <= now` whose active allocations overlap `range` for any resource of that dealership (or the vehicle). Called before the write in `CreateBooking` (F02, both modes), `RescheduleAppointment` (F09) and F12 moves. |
| D-10.10 | Expiring a hold writes no outbox message; confirming writes `AppointmentConfirmed`. |
| D-10.11 | Web countdown uses the server clock: offset = server `Date` header − browser time, measured on the hold response (seam `useServerClock`). |
| D-10.12 | After F10, the customer web always holds first, then confirms (FR-02.6). The API still accepts `mode: CONFIRM` from customers for other clients. |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-10.a | Should leaving the flow (back button) release the hold at once? | No API call; the next hold or the job ends it. |
| Q-10.b | Should holds show on the staff Appointments list? | Yes, as "On hold" (F05 D-05.2 already lists `HELD`). |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00–F09 are merged: `CreateBooking` (`mode: CONFIRM` only), `AppointmentWriter::create` (supports `status HELD` + `expiresAt`, F04), `TransitionAppointment` (`HELD → CONFIRMED, CANCELLED, EXPIRED`), the busy-interval reader ignoring expired holds (F04 TC-04-27), `appointments_held_expiry_idx` (F04), `HOLD_MINUTES=5` in `.env.example`, the `scheduler` container running `schedule:work`; web `BookingDraft`, `ContactDetailsPage`, `ChooseTimePage`, `SlotGrid`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-10.1 | `mode: HOLD` in `CreateBooking` + one-hold-per-customer index. |
| S-10.2 | `POST /appointments/{id}/confirm` + action `ConfirmHold`. |
| S-10.3 | Command `holds:expire` (every 30 s). |
| S-10.4 | Seam `ExpiredHoldReleaser` wired into F02, F09 (and F12 later). |
| S-10.5 | Web: "Hold and continue", Temporary hold screen with countdown and expired state, `useServerClock`. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-10.1 | A `HELD` appointment's allocations are active, so EXCLUDE protects the held time like a real booking (FR-10.1, AC-10.4). | `AppointmentWriter::create` with `status HELD`. |
| INV-10.2 | At most one `HELD` appointment per customer at any time. | Partial unique index (D-10.4); new hold expires the old one in the same transaction. |
| INV-10.3 | A hold ends in exactly one of `CONFIRMED`, `EXPIRED`, `CANCELLED`; the job and a confirm can never both apply (AC-10.3). | Both write through `updateVersioned` with the version they read; the loser gets 0 rows. |
| INV-10.4 | Confirm succeeds only if `status = HELD`, `expires_at > now` (app clock) and the version matches; it sets `CONFIRMED`, `vehicle_id`, `reminder_opt_in`, `expires_at = NULL` and ensures one active `VEHICLE` allocation for the same period, in one transaction. | `ConfirmHold`. |
| INV-10.5 | Expiring a hold deactivates all its allocations in the same transaction (INV-04.5). | `TransitionAppointment(EXPIRED)`. |
| INV-10.6 | An expired hold never blocks a new booking for long: the snapshot ignores it (F04) and `ExpiredHoldReleaser` deactivates it before the write. | Every write flow listed in D-10.9. |
| INV-10.7 | Only the customer who owns the hold can confirm it; staff cannot hold. | `findVisibleTo` + role check (D-10.1). |
| INV-10.8 | `expires_at` and every expiry comparison use the app clock passed as a parameter (README D-G.6). | `CreateBooking`, `ConfirmHold`, job, releaser. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Confirm (INV-10.4) | Hold 6 minutes old; confirm exactly at `expires_at`; vehicle busy; another customer's hold; already confirmed with the same key / another key | TC-10-02, TC-10-08, TC-10-09, TC-10-10, TC-10-11 |
| Race (INV-10.3) | Job and confirm at the same moment, 100 runs | TC-10-03 |
| One hold (INV-10.2) | Second hold ends the first; second hold fails → first kept; two holds in parallel with no current hold; two holds in parallel while one is already held | TC-10-05, TC-10-06, TC-10-07, TC-10-21 |
| Release (INV-10.6) | Expired hold blocks the only technician; job not running | TC-10-12 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/database/migrations/2026_11_10_000001_add_one_hold_per_customer_index.php` | DB. |
| `api/app/Domain/Booking/Services/BookingService.php` (`create`) | `mode HOLD` branch (D-10.1–10.4), calls `ExpiredHoldReleaser`. |
| `api/app/Domain/Booking/Services/BookingService.php`, `BookingServiceInterface.php` | Add `confirmHold()` = B7 (`ConfirmHold`). |
| `api/app/Domain/Booking/Services/ExpiredHoldReleaser(.php, Interface.php)` | D-10.9; bound in `DomainServiceProvider`. |
| `api/app/Domain/Booking/Exceptions/{HoldExpired,HoldNotAllowed}.php` | 410, 422. |
| `api/app/Console/Commands/ExpireHolds.php`, `api/routes/console.php` | `holds:expire` → `Schedule::command('holds:expire')->everyThirtySeconds()->withoutOverlapping()`. |
| `api/app/Http/Requests/{CreateAppointmentRequest,ConfirmHoldRequest}.php` | `mode: HOLD`; confirm body. |
| `api/app/Http/Controllers/Api/V1/AppointmentController.php` | Add `confirm`. |
| `api/app/Providers/AppServiceProvider.php` (`boot()`) | Ability `confirm-hold` (customer). |
| `api/app/Domain/Booking/Services/BookingService.php` (`reschedule`) | Call `ExpiredHoldReleaserInterface` before `moveTo`. |
| `web/src/customer/booking/{ChooseTimePage,HoldPage}.tsx`, `web/src/shared/time/useServerClock.ts`, `web/src/shared/api/appointments.ts` (`useCreateHold`, `useConfirmHold`) | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

```php
// illustrative
interface BookingServiceInterface {
    /** `ConfirmHold`. */
    public function confirmHold(User $actor, string $appointmentId, int $expectedVersion,
                             string $vehicleId, bool $reminderOptIn, CarbonImmutable $now): Appointment; // throws HoldExpired, AppointmentModified, VehicleAlreadyBooked, InvalidStatusTransition
}
interface ExpiredHoldReleaserInterface {
    /** INV-10.6. Returns the number of holds expired. Each in its own transaction, before the caller's write. */
    public function releaseBlocking(string $dealershipId, TimeRange $range, ?string $vehicleId, CarbonImmutable $now): int;
}
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `ExpiredHoldReleaser` | F02, F09, F12 |
| `useServerClock` | Any countdown or "now"-based UI (F07/F09 change window can switch to it) |

FCs: FC-10.1 (staff holds for phone calls) → `HoldNotAllowed` is the only gate; FC-10.2 (per-dealership hold length) → `HOLD_MINUTES` becomes a dealership column.

## B7. Algorithms

**Create a hold (`CreateBooking`, `mode HOLD`)**
1. Same checks in the F02 D-02.7 order (vehicle optional; if given, ownership checked).
2. `ExpiredHoldReleaser::releaseBlocking(dealership, range, vehicleId, now)`.
3. Snapshot + `find` → no pair → 409 (+ alternatives, F06).
4. Transaction: lock the customer row (`FOR UPDATE`, D-10.4); then if the customer has a `HELD` appointment (read **after** the lock) → `TransitionAppointment(EXPIRED)` with its read version (inside this transaction) → `AppointmentWriter` insert of the new `HELD` (`expires_at = now + HOLD_MINUTES`) with the pair loop of F04 → commit. 23P01 on any try rolls back the old hold's expiry too.
5. 201 `AppointmentResource` (`status HELD`, `expiresAt`) + `ETag: "1"`.

**Confirm (`ConfirmHold`)**
1. `findVisibleTo` (customer, own) → 404.
2. `status ≠ HELD`: version ≠ expected → 412; else 422 `INVALID_STATUS_TRANSITION`.
3. `expires_at <= now` → `TransitionAppointment(EXPIRED, expected)`; success or `AppointmentModified` with status `EXPIRED` → 410 `HOLD_EXPIRED`; other 412 → rethrow.
4. Vehicle must belong to the customer → else 404 `VEHICLE_NOT_FOUND`.
5. Transaction: `updateVersioned(status CONFIRMED, vehicle_id, reminder_opt_in, expires_at NULL)` (0 rows → 412) → if the vehicle allocation is missing or for another vehicle: deactivate it and insert `VEHICLE` with the same period (23P01 → rollback, 409 `VEHICLE_ALREADY_BOOKED`) → history `CONFIRMED` → outbox `AppointmentConfirmed` → commit.
6. 200 + new `ETag`.

**Job `holds:expire`**: `SELECT id, version FROM appointments WHERE status = 'HELD' AND expires_at <= :now ORDER BY expires_at LIMIT 500` → for each: `TransitionAppointment(EXPIRED, version)`; `AppointmentModified`/`InvalidStatusTransition` → skip; log one line with the count.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| `mode` not `CONFIRM`/`HOLD`; confirm without `vehicleId`; bad types | 400 | `VALIDATION_ERROR` |
| Staff send `mode: HOLD` | 422 | `HOLD_NOT_ALLOWED` |
| Staff call confirm | 403 | `FORBIDDEN` |
| Hold not visible (another customer's) | 404 | `APPOINTMENT_NOT_FOUND` |
| Vehicle not the customer's | 404 | `VEHICLE_NOT_FOUND` |
| No slot for the hold | 409 | `NO_AVAILABILITY` |
| Vehicle busy at the held time | 409 | `VEHICLE_ALREADY_BOOKED` |
| Hold already ended | 410 | `HOLD_EXPIRED` |
| Old version (including losing to the job) | 412 | `APPOINTMENT_MODIFIED` |
| Not `HELD` (e.g. already confirmed) | 422 | `INVALID_STATUS_TRANSITION` |
| Missing `If-Match` | 428 | `PRECONDITION_REQUIRED` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-10.1 | A signed-in customer picks 10:00 | Taps "Hold and continue" | 201, status `HELD`, countdown starts at 05:00 | TC-10-01, TC-10-14 |
| AC-10.2 | A hold created 6 minutes ago | Confirm | 410 `HOLD_EXPIRED` | TC-10-02 |
| AC-10.3 | The expiry job and the customer's confirm run at the same moment | Both finish | The booking is either `CONFIRMED` or `EXPIRED`, never both; the loser gets 412 or skips | TC-10-03 |
| AC-10.4 | Customer A holds 10:00 | Customer B asks for free times | 10:00 is not free for B | TC-10-04 |
| AC-10.5 (new) | Customer holds 10:00 | Holds 11:00 | The 10:00 hold is `EXPIRED` (allocations inactive); the 11:00 hold is `HELD` | TC-10-05 |
| AC-10.6 (new) | Customer holds 10:00; 11:00 is full | Tries to hold 11:00 | 409; the 10:00 hold is still `HELD` | TC-10-06 |
| AC-10.7 (new) | A hold of a valid customer | Confirm with a vehicle | 200 `CONFIRMED`, `expiresAt null`, 3 active allocations, 1 `AppointmentConfirmed` event | TC-10-08 |
| AC-10.8 (new) | The vehicle has another booking at the held time | Confirm with it | 409 `VEHICLE_ALREADY_BOOKED`; still `HELD` | TC-10-09 |
| AC-10.9 (new) | An expired hold holds the only technician; the job has not run | Another customer books that time | 201 (the hold is expired on the way) | TC-10-12 |
| AC-10.10 (new) | 3 expired holds and 1 valid hold | `holds:expire` | 3 `EXPIRED` with inactive allocations; the valid one untouched | TC-10-13 |
| AC-10.11 (new) | A staff advisor | `POST /appointments` with `mode: HOLD` | 422 `HOLD_NOT_ALLOWED` | TC-10-15 |
| AC-10.12 (new) | Temporary hold screen; countdown reaches 0 or confirm returns 410 | Screen | "Your hold has expired" + "Pick a time again"; going back keeps the entered vehicle | TC-10-16 |

## B10. Implementation order

1. Ticket 01 (prefactor): `ExpiredHoldReleaser` (no callers yet) + `useServerClock`; nothing users can see.
2. Tracer bullet: `mode HOLD` + confirm + Temporary hold screen, then `/code-review`.
3. One hold per customer.
4. `holds:expire` job + releaser wired into F02/F09.
5. Race test + acceptance.

## B11. Definition of Done

Every TC green (TC-10-03 in `@group slow` too); README §8 green; the scheduler container shows `holds:expire` in `php artisan schedule:list`.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-10.1 | The scheduler container stops → holds pile up | INV-10.6 releaser; SDD §14 alert if the job has not run for 2 minutes (F08 FC) |
| R-10.2 | Browser clock skew makes the countdown wrong | D-10.11 server clock |
| R-10.3 | Holds without a vehicle let a customer hold the same time twice with two cars | One hold per customer (INV-10.2) |
| R-10.4 | The vehicle allocation added at confirm clashes | D-10.2: 409 and the hold stays; the customer picks another vehicle or time |
