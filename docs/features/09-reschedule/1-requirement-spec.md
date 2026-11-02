# F09 · Reschedule · Requirement spec (RQ)

Branch: `feature/09-reschedule` · Priority: Could · Shared conventions: [README](../README.md) · Read first: SDD §10 "Example: reschedule"

---

# PART A · For readers

## A1. Story

**Why this feature exists.** Customers change plans. They want to move a booking to another time **without losing the old one** if the new time is taken. Advisors do the same at the desk while on the phone.

**The problem.** A naive "cancel, then book again" loses the old slot when the new time is full, and two people changing the same booking at once can silently overwrite each other.

**What it does.**
- An advisor or the customer picks a new start time for a `CONFIRMED` booking.
- The system checks the version the user saw, frees the old slot, takes the new one (keeping the same technician when possible), and writes a "rescheduled" message to the outbox — all in one transaction.
- If anything fails (new time taken, vehicle busy, someone else changed the booking), **nothing changes**: the customer keeps the old time, technician and bay.
- The screens show "Current time → New time" before the user confirms.

**Who uses it, where.** Advisors: staff web → Appointment details → "Reschedule". Customers: app → booking details → "Change time".

**Inputs.** The booking, the new start time, whether to prefer the same technician (advisor screen; default yes).

**Outcome.** The same booking (same id and reference) at the new time, with a new version.

**What it does not do.** Change the service type, the vehicle or the dealership; move bookings that are on hold, checked in or later; bulk moves.

## A2. Usage steps

**Advisor** (`Admin/screenshots/06-reschedule.png`): Appointment details → "Reschedule" → see the current booking → pick a date and a free time (the booking's own time counts as free) → "Change to Mon 2 Nov, 14:00–15:00" → "Confirm reschedule" → back on the details page with the new time.

**Customer** (`Customer/screenshots/07-change-time.png`): My bookings → details → "Change time" → pick a day and a free time → "Current time Mon 2 Nov · 10:00 → New time …" → "Confirm new time".

## A3. Screens and deviations from the drafts

| Draft | Spec | Reason |
| --- | --- | --- |
| Staff: "Prefer keeping technician Trần Minh An" checkbox | Kept; default checked | FR-09.3 |
| Customer: no "keep technician" option | The customer flow always prefers the same technician | FR-09.3 ("customers like to see the same person") |
| Draft has no error states | 409 shows the F06 suggestions; 412 shows the "changed by someone else" alert; `TOO_LATE_TO_RESCHEDULE` shows the dealership phone | F04, F06, BR-09.1 |

## A4. Key rules in plain words

- Only **Confirmed** bookings can be moved.
- Customers can move their booking only while at least 2 hours remain before the **current** start (same as cancel). Advisors have no limit.
- The new time follows the booking rules: future, 15-minute step, within 90 days, inside one day's opening hours.
- The new time must differ from the current one.
- If the new time is taken, the old booking stays exactly as it was.
- Moving inside the booking's own slot (10:00–11:00 → 10:30–11:30 in the same bay) works: a booking never clashes with itself.

## A5. Out of scope

Changing service/vehicle/dealership, moving holds, moving several bookings at once (F12 reassigns, which keeps the time).

## A6. Success criteria

- No reschedule ever loses the old slot when the new time fails (AC-09.1).
- A reschedule and a cancel sent at once never both win (AC-04.6 on real endpoints).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-09.1 | Endpoint `POST /api/v1/appointments/{id}/reschedule` with body `{ newStart, keepTechnician }`; headers `If-Match` and `Idempotency-Key`. |
| D-09.2 | Advisors (own dealership) and customers (own bookings) may reschedule; foremen and admins → 403 (README §4). |
| D-09.3 | Only `CONFIRMED` (BR-09.1). Any other status → 422 `INVALID_STATUS_TRANSITION`. |
| D-09.4 | Customers: `current start − now ≥ 2 hours` (BR-09.1, same as F07 D-07.3), else 422 `TOO_LATE_TO_RESCHEDULE` (new code) with `dealershipPhone`. Uses `CustomerChangePolicy` (F07). |
| D-09.5 | `newStart` equal to the current start → 422 `RESCHEDULE_SAME_TIME` (new code). |
| D-09.6 | `keepTechnician` (default `true`; customers cannot set it) → F03 `preferTechnicianId` = the current technician; the current bay is always preferred (`preferBayId`), so a small move keeps the bay when free. |
| D-09.7 | The search ignores the booking's own allocations (`excludeAppointmentId`), and the write follows `AppointmentWriter::moveTo` (F04 INV-04.7): versioned update → deactivate old → insert new → outbox → commit. |
| D-09.8 | The reference (`APT-…`) and the id never change. The history row `RESCHEDULED` stores `{from: {start, end, technicianId, bayId}, to: {…}}`. |
| D-09.9 | Outbox `AppointmentRescheduled` (SMS, EMAIL, DMS). Payload holds the old and the new time. |
| D-09.10 | 409 `NO_AVAILABILITY` includes F06 suggestions computed with `excludeAppointmentId`. 409 `VEHICLE_ALREADY_BOOKED` when the vehicle has another booking at the new time. |
| D-09.11 | F09 adds the "Reschedule existing booking" button to the F02 `VEHICLE_ALREADY_BOOKED` card (advisor only, when the existing booking is `CONFIRMED`). |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-09.a | Can a reschedule change the day? | Yes, any day within 90 days. |
| Q-09.b | Does the customer get an SMS when an advisor reschedules? | Yes (D-09.9), same as when the customer does it. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00–F08 are merged: `AppointmentWriter::moveTo`, `updateVersioned`, `findVisibleTo`, `BookingTimeRules`, `CandidateFinder` (`preferTechnicianId`, `preferBayId`, `excludeAppointmentId`), `AvailabilityCalculator` and `AlternativeTimesFinder` (with `excludeAppointmentId`), `CustomerChangePolicy`, `OutboxWriter`, `Tracer`; web `SlotGrid`, `AlternativeChips`, `BookingErrorCard`, `AppointmentModifiedAlert`, `appointmentQuery`, `AppointmentDetailsLayout`, `changeWindow.ts`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-09.1 | `POST /api/v1/appointments/{appointment}/reschedule`. |
| S-09.2 | Action `RescheduleAppointment`; exceptions `TooLateToReschedule`, `RescheduleSameTime`. |
| S-09.3 | Staff web: Reschedule screen + "Reschedule" button on details + "Reschedule existing booking" in the F02 card. |
| S-09.4 | Customer web: Change time screen + "Change time" button. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-09.1 | A failed reschedule changes nothing: status, times, technician, bay, allocations and version stay as before (FR-09.2). | The whole move is one transaction (`moveTo`); any exception rolls it back. |
| INV-09.2 | The version check comes first and locks the row (FR-09.1, INV-04.7); a retry after 23P01 reruns the whole transaction with the **same** expected version. | `moveTo`. |
| INV-09.3 | Old allocations are deactivated **before** the new ones are inserted, so a move within the booking's own slot never clashes with itself (AC-09.2). | `moveTo` steps 2–3. |
| INV-09.4 | Candidate search ignores the booking's own busy intervals and prefers its current technician (if `keepTechnician`) and bay (FR-09.3). | `AllocationRequest(excludeAppointmentId, preferTechnicianId, preferBayId)`. |
| INV-09.5 | Only `CONFIRMED` moves; status read through `findVisibleTo`; if the version is stale, 412 beats 422 (F04 TC-04-26). | `RescheduleAppointment`. |
| INV-09.6 | Customer 2-hour rule is checked on the **current** `start_at` from the same read as the status; a parallel change makes the versioned update fail (412), so the rule cannot be bypassed. | `CustomerChangePolicy`. |
| INV-09.7 | The new range obeys `BookingTimeRules` (F02 INV-02.4, 02.5) in the dealership's time zone. | Before the search. |
| INV-09.8 | Exactly one `RESCHEDULED` history row and one `AppointmentRescheduled` outbox event per successful move; none on failure. | Inside the `moveTo` transaction. |
| INV-09.9 | `appointments.technician_id`, `bay_id`, `start_at`, `end_at` always equal the active allocations after the move (INV-04.6). | `moveTo`. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Nothing changes on failure (INV-09.1) | New time full; vehicle busy at the new time; stale version; 23P01 on every pair | TC-09-01, TC-09-06, TC-09-03, TC-09-07 |
| Own slot (INV-09.3) | 10:00–11:00 → 10:30–11:30 same bay; → 09:30–10:30 | TC-09-02, TC-09-08 |
| Same technician (INV-09.4) | Same technician free; same technician busy, another free; `keepTechnician=false` | TC-09-04, TC-09-05 |
| Status/time rules (INV-09.5–09.7) | `HELD`, `CHECKED_IN`, `CANCELLED`; customer 1 hour before; same time; past; outside hours | TC-09-09, TC-09-10, TC-09-11, TC-09-13 |
| Concurrency (INV-09.2) | Cancel + reschedule on the same version; two reschedules at once | TC-09-12, TC-09-14 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Booking/Actions/RescheduleAppointment.php` | B7. |
| `api/app/Domain/Booking/Exceptions/{TooLateToReschedule,RescheduleSameTime}.php` | README §5. |
| `api/app/Http/Requests/RescheduleAppointmentRequest.php` | API §B2. |
| `api/app/Http/Controllers/Api/V1/AppointmentController.php` | Add `reschedule`. |
| `api/app/Providers/AuthServiceProvider.php` | Ability `reschedule-appointment` (advisor, customer). |
| `api/routes/api.php` | Route with `auth:sanctum`, `can:reschedule-appointment`, `idempotent`, `if-match`. |
| `web/src/staff/appointments/ReschedulePage.tsx`, `web/src/customer/bookings/ChangeTimePage.tsx`, `web/src/shared/api/appointments.ts` (`useRescheduleAppointment`) | WEB. |
| `web/src/staff/appointments/AppointmentDetailsPage.tsx`, `web/src/customer/bookings/BookingDetailsPage.tsx`, `web/src/shared/booking/BookingErrorCard.tsx` | Buttons (D-09.11). |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [5-web-spec.md](5-web-spec.md). No new tables ([3-database-spec.md](3-database-spec.md)).

```php
// illustrative — INV-09.1–09.8
final class RescheduleAppointment {
    public function __invoke(User $actor, string $appointmentId, int $expectedVersion,
                             CarbonImmutable $newStart, bool $keepTechnician, CarbonImmutable $now): Appointment;
}
```

## B6. Seams

Uses: `findVisibleTo`, `BookingTimeRules`, `CustomerChangePolicy`, `AllocationSnapshotRepository`, `CandidateFinder`, `AppointmentWriter::moveTo`, `AlternativeTimesFinder`; web `SlotGrid` (with `excludeAppointmentId`), `AlternativeChips`, `AppointmentModifiedAlert`.

New: none shared. `RescheduleAppointment` is the template F12 extend follows (time change + reassignment).

## B7. Algorithm (`RescheduleAppointment`)

1. `appointment = findVisibleTo(actor, id)` (404).
2. `status ≠ CONFIRMED` → if `version ≠ expected` → 412; else 422 `INVALID_STATUS_TRANSITION`.
3. Customer and `!CustomerChangePolicy::allows(appointment, now)` → 422 `TOO_LATE_TO_RESCHEDULE`.
4. `newStart == appointment.start` → 422 `RESCHEDULE_SAME_TIME`.
5. `newRange = BookingTimeRules::check(newStart, serviceType.duration, openingHours, now)` (400/422).
6. Snapshot for the new local day → `find(AllocationRequest(newRange, excludeAppointmentId = id, preferTechnicianId = keepTechnician ? technician_id : null, preferBayId = bay_id))` → none → 409 `NO_AVAILABILITY` with `reason` and `alternatives = AlternativeTimesFinder::near(…, excludeAppointmentId = id)`.
7. `AppointmentWriter::moveTo(id, expected, newRange, pairs, RESCHEDULED, AppointmentRescheduled, now)`:
   - 0 rows on the versioned update → 412 (no retry).
   - 23P01 TECHNICIAN/BAY → next pair (max 3, same expected version); none left → 409 `NO_AVAILABILITY` `SLOT_TAKEN` + `alternatives`.
   - 23P01 VEHICLE → 409 `VEHICLE_ALREADY_BOOKED`.
8. 200 `AppointmentResource` + new `ETag`.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| `newStart` missing/no offset/not on a 15-minute step; `keepTechnician` not boolean | 400 | `VALIDATION_ERROR` |
| Missing `Idempotency-Key` | 400 | `IDEMPOTENCY_KEY_REQUIRED` |
| Foreman, admin | 403 | `FORBIDDEN` |
| Booking not visible | 404 | `APPOINTMENT_NOT_FOUND` |
| New time taken / no pair | 409 | `NO_AVAILABILITY` (+ `alternatives`) |
| Vehicle has another booking at the new time | 409 | `VEHICLE_ALREADY_BOOKED` |
| Old version | 412 | `APPOINTMENT_MODIFIED` |
| Status not `CONFIRMED` | 422 | `INVALID_STATUS_TRANSITION` |
| Customer less than 2 hours before the current start | 422 | `TOO_LATE_TO_RESCHEDULE` |
| Same time as now | 422 | `RESCHEDULE_SAME_TIME` |
| New time in the past / > 90 days / outside opening hours | 422 | `START_IN_PAST` / `START_TOO_FAR_AHEAD` / `OUTSIDE_OPENING_HOURS` |
| Missing `If-Match` | 428 | `PRECONDITION_REQUIRED` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-09.1 | A booking at 09:00; 14:00 is full | Reschedule to 14:00 | 409; the 09:00 booking is still `CONFIRMED` with the same technician, bay and version | TC-09-01 |
| AC-09.2 | A booking 10:00–11:00 in Bay 2; Bay 2 is free 11:00–11:30 | Move to 10:30–11:30 | 200; the booking does not clash with itself | TC-09-02 |
| AC-09.3 | The advisor cancelled the booking (version 4) | The customer reschedules with `If-Match: "3"` | 412 `APPOINTMENT_MODIFIED`; the app shows the latest data | TC-09-03, TC-09-17 |
| AC-09.4 (new) | Same technician free at the new time | Reschedule with `keepTechnician` | Same technician kept | TC-09-04 |
| AC-09.5 (new) | Same technician busy at the new time, another qualified one free | Reschedule | 200 with the other technician | TC-09-05 |
| AC-09.6 (new) | The vehicle has another booking at the new time | Reschedule | 409 `VEHICLE_ALREADY_BOOKED`; nothing changed | TC-09-06 |
| AC-09.7 (new) | A `CHECKED_IN` booking | Reschedule | 422 `INVALID_STATUS_TRANSITION` | TC-09-09 |
| AC-09.8 (new) | Customer, current start in 1 hour | Reschedule | 422 `TOO_LATE_TO_RESCHEDULE` | TC-09-10 |
| AC-09.9 (new) | — | Reschedule to the current start | 422 `RESCHEDULE_SAME_TIME` | TC-09-11 |
| AC-09.10 (new, AC-04.6 on real endpoints) | A booking at version 3 | Cancel and reschedule sent at once, both `If-Match: "3"` | One 200, the other 412; final version 4 | TC-09-12 |
| AC-09.11 (new) | A successful reschedule | Look at history and outbox | 1 `RESCHEDULED` row with from/to; 1 `AppointmentRescheduled` event; reference unchanged | TC-09-15 |
| AC-09.12 (new) | Staff Reschedule screen | Pick a new time, confirm | "Change to …" summary; after confirm the details page shows the new time | TC-09-16 |
| AC-09.13 (new) | Customer Change time screen, own time shown | Open the grid | The booking's own slot is selectable (not struck through) | TC-09-18 |

## B10. Implementation order

1. Ticket 01 (prefactor): none needed beyond checks — confirm `moveTo` and `excludeAppointmentId` from F04/F06 are reusable as-is; record it.
2. Tracer bullet: endpoint + staff Reschedule screen, then `/code-review`.
3. Rules (status, 2 hours, same time) + 409/412 handling.
4. Customer Change time screen + buttons.
5. Concurrency + acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `AssertsNoOverlap` after every test.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-09.1 | Inserting new allocations before deactivating old ones → self-clash | INV-09.3 + TC-09-02 |
| R-09.2 | A retry after 23P01 re-reads the version → hides a concurrent change | INV-09.2: same expected version on retry |
| R-09.3 | An expired hold blocks the new time (EXCLUDE) | F10 adds the release step inside the booking flow (FC-10 in F10) |
