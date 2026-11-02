# F12 · Day-of-service operations · Requirement spec (RQ)

Branch: `feature/12-day-of-service` · Priority: Could · Shared conventions: [README](../README.md) · Read first: SDD §6 ("foreman and advisor"), §10 (extend, reassign rows)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** Plans change on the day: cars arrive or do not, and jobs run late. The foreman needs to record what happens and absorb overruns, without ever double-booking and without silently cancelling customers.

**What it does.**
- **Status actions** on a booking: Check in (car arrived), Start work, Mark completed, Mark no-show. Completed and no-show free the slot at once.
- **Extend** a job that runs late (15 minutes to 2 hours). The extra time is checked like a new booking. If it clashes with the next booking on the same technician or bay, that booking is moved to another technician/bay at the same time, or — if nothing is free — flagged "contact the customer". It is never cancelled automatically.
- **Workshop board**: a timeline of the day by bay, the technicians of the day, and an alert when a job overruns.
- **Contact list**: every booking flagged "contact the customer", so advisors call them.

**Who uses it.** Workshop foreman (all actions); service advisor (check in, no-show, board, contact list).

**What it does not do.** Technician absences and bay outages (README D-G.16: no attendance or leave; a technician who leaves is disabled in Master data, F01), time tracking per task, parts, automatic cancellation, rescheduling to another day (advisors do that with F09).

## A2. Usage steps

1. A car arrives → advisor or foreman opens the booking → "Check in".
2. Foreman → "Start work" → later "Mark completed".
3. The customer does not come → after the start time, "Mark no-show".
4. The board shows "Bay 3 is running 20 minutes over" → "Extend 30 min" → the next booking in Bay 3 moves to another bay, or appears in "contact the customer".
5. Advisors open the contact list, call each customer, then reschedule (F09) or cancel (F07).

## A3. Screens and deviations from the drafts

Drafts: `Admin/screenshots/07-workshop-board.png`, `05-appointment-details.png` (action buttons). The draft `08-report-absence-reassign.png` is not built (D-12.5).

| Draft | Spec | Reason |
| --- | --- | --- |
| Board buttons "Report bay out of service", "Report absence" | Removed | Absences and bay outages are out of scope (README D-G.16) |
| Technicians table: Skills, Shift, Availability "Absent", action "Report absence" | Technician · Level · Booked · Status (Working / Overrun); no action | D-12.16 |
| Board legend shows "On hold" blocks | Kept (holds occupy bays) | F10 |

## A4. Key rules in plain words

- Allowed status order: Confirmed → Checked in → In progress → Completed; Confirmed → No-show. Anything else is refused.
- Check in only on the day of the booking (dealership's date). No-show only after the start time.
- Completed and no-show free the technician, bay and vehicle at once.
- Extending is allowed for checked-in and in-progress jobs and may run past closing time (it records reality).
- A booking pushed out by an overrun is never cancelled; it is flagged for a call and must be rescheduled before check-in.

## A5. Out of scope

Technician absences and bay outages, per-task timers, partial completion, automatic SMS to affected customers beyond the "technician changed" message, assigning a specific technician by hand.

## A6. Success criteria

- AC-12.4: after an extend, the next booking is moved or flagged, never cancelled, with no overlap.
- After every F12 operation, the overlap query returns 0 rows and M-04.2 stays 0 (with the D-12.9 exception).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-12.1 | Status endpoints (all `POST`, `If-Match` + `Idempotency-Key`): `/appointments/{id}/check-in` (advisor, foreman), `/start` (foreman), `/complete` (foreman), `/no-show` (advisor, foreman), `/extend` (foreman). They go through `TransitionAppointment` (F04) and set `checked_in_at`, `started_at`, `completed_at`. |
| D-12.2 | Check-in only on the booking's local date → else 422 `NOT_SERVICE_DAY` (new code). No-show only when `now ≥ start_at` → else 422 `NO_SHOW_BEFORE_START` (new code, BR-12.1). Check-in of a booking displaced by an overrun (`contact_required_reason = 'OVERRUN'`, no active allocation, D-12.9) → 422 `NEEDS_RESCHEDULE` (new code): it holds no bay or technician, so it must be rescheduled first (F09 allows the same time for it, F09 D-09.5). Start and complete need `CHECKED_IN`/`IN_PROGRESS`, so a displaced booking can never run without allocations. |
| D-12.3 | Extend: body `{ minutes }` with minutes ∈ {15, 30, 45, 60, 90, 120}; status `CHECKED_IN` or `IN_PROGRESS`; no opening-hours check (the job is physically running). |
| D-12.4 | Extend runs in **one** transaction: versioned update of the job (locks it) → for each booking whose active `TECHNICIAN` or `BAY` allocation overlaps `[old end, new end)` on the job's technician or bay: try a reassignment at the same time (F03 with the extension treated as busy); found → move it; none → displace it (D-12.9) → deactivate the job's allocations → insert the extended ones → commit. A 412 or 23P01 on a neighbour rolls everything back and the whole extend is retried once with fresh reads; then 409 `NO_AVAILABILITY` (`reason: SLOT_TAKEN`). |
| D-12.5 | Technician absences and bay outages (FR-12.3, FR-12.4, AC-12.3) are out of scope (README D-G.16, user decision 2026-11-03). Decisions D-12.6, D-12.7, D-12.10–D-12.12 of the earlier version are withdrawn; no `technician_absences` or `bay_outages` tables. |
| D-12.8 | A neighbour that cannot be reassigned during an extend gets `contact_required_reason = 'OVERRUN'` through a versioned update and a `CONTACT_REQUIRED` history row. It keeps its status. F09 reschedule, F07 cancel and a later reassignment clear the flag. |
| D-12.9 | Displacement on overrun: a flagged `OVERRUN` booking also has all its allocations deactivated (the bay/technician are physically taken). This is the only case where a `CONFIRMED` booking holds no slot; M-04.2 and `AssertsNoOverlap` treat `contact_required_reason = 'OVERRUN'` as allowed. |
| D-12.13 | Board: `GET /dealerships/{d}/workshop-board?date=` (all staff). Blocks show `HELD`, `CONFIRMED`, `CHECKED_IN`, `IN_PROGRESS`, `COMPLETED`, and `CONFIRMED` bookings displaced by an overrun (drawn hatched, no bay); cancelled, no-show and expired are hidden. An overrun = `IN_PROGRESS` with `end_at < now`. |
| D-12.14 | Contact list = F05 list with `contactRequired=true` (F05 FC-05.3); `date` is then optional and the list holds every flagged `CONFIRMED` booking from today on (F05 D-05.1). |
| D-12.15 | The board returns each block's and alert's `version`, so the board's "Extend 30 min" sends `If-Match` from the data the foreman saw (F04 INV-04.4). |
| D-12.16 | Board technicians table: active technicians of the dealership with their level (F01), booked minutes that day, and status `WORKING` or `OVERRUN` (has an overrunning job). No shifts or absences (README D-G.16). |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-12.a | Can a foreman pick the new technician by hand? | No; the system picks with F03. |
| Q-12.b | Should completing early free the rest of the slot? | Yes; all allocations are deactivated at completion. |
| Q-12.c | Can an advisor extend a job? | No; foreman only. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00–F11 are merged: `TransitionAppointment`, `AppointmentWriter::moveTo`, `updateVersioned`, `findVisibleTo`, `CandidateFinder` with `prefer*`/`excludeAppointmentId`, `BusyIntervalReader` (allocations), `ExpiredHoldReleaser`, `OutboxWriter` (`AppointmentReassigned` already in the CHECK list), `appointments.contact_required_reason` (CHECK `OVERRUN`), `checked_in_at`, `started_at`, `completed_at` (F04), `appointments_contact_required_idx` (F04), technicians with `level` (F01), F05 list, F09 reschedule, F07 cancel; web `AppointmentDetailsLayout`, `appointmentQuery`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-12.1 | 5 status endpoints (D-12.1, D-12.2). |
| S-12.2 | Extend (D-12.3, D-12.4, D-12.8, D-12.9). |
| S-12.5 | Workshop board endpoint; F05 `contactRequired` filter; clearing the flag in F07/F09 paths. |
| S-12.6 | Web: Workshop board, action buttons and contact banner on Appointment details. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-12.1 | Only the transitions of D-04.8 are allowed (FR-12.1); others → 422 `INVALID_STATUS_TRANSITION`. | `TransitionAppointment`. |
| INV-12.2 | `COMPLETED` and `NO_SHOW` deactivate every allocation in the same transaction (FR-12.2). | `TransitionAppointment` (INV-04.5). |
| INV-12.3 | No two active allocations ever overlap after extend, reassign or displacement (INV-04.1). | EXCLUDE; `AssertsNoOverlap`. |
| INV-12.4 | Extend is all-or-nothing: the job's extension and every neighbour's move/displacement commit together or not at all. | One transaction (D-12.4). |
| INV-12.6 | No booking is ever cancelled by F12 (FR-12.5). | No code path sets `CANCELLED`; test. |
| INV-12.7 | A reassigned neighbour keeps its start, end and vehicle; only technician and/or bay change; `technician_id`/`bay_id` match the active allocations. | `moveTo` with the same range. |
| INV-12.9 | `contact_required_reason` is set and cleared only through versioned writes (with a history row). | `updateVersioned`. |
| INV-12.10 | Staff act only on their dealership's bookings (404 otherwise); roles per README §4. | `{appointment}` binding / `{dealership}` binding / Gates. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Status actions (INV-12.1, D-12.2) | Check in a `COMPLETED`; check in tomorrow's booking; no-show before start; complete without start; check in a displaced booking | TC-12-02, TC-12-05, TC-12-06, TC-12-07, TC-12-27 |
| Extend (INV-12.4, D-12.4, D-12.9) | No neighbour; neighbour reassignable; neighbour not reassignable; neighbour changed concurrently; job version stale | TC-12-08, TC-12-04, TC-12-09, TC-12-10, TC-12-11 |
| Scope (INV-12.10) | Another dealership's booking or board | TC-12-17 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Workshop/Services/WorkshopService(.php, Interface.php)` | `checkIn`, `start`, `complete`, `markNoShow` (wrap `TransitionAppointment` + D-12.2 rules), `extend` (`ExtendAppointment`, D-12.4, D-12.9), `board`; README §6.3. |
| `api/app/Domain/Workshop/Services/Reassigner(.php, Interface.php)` | Moves one extend neighbour inside the caller's transaction. Both pairs bound in `DomainServiceProvider`. |
| `api/app/Domain/Allocation/ResourceSnapshot.php` | `withBusy(resourceType, id, range)` (pure, returns a copy) for the extension (D-12.4). |
| `api/app/Domain/Booking/Services/AppointmentWriter.php` | `moveTo` clears `contact_required_reason`; `displace(id, expected, reason)`. |
| `api/app/Domain/Booking/Services/BookingService.php` (`cancel`) | Clear `contact_required_reason`. |
| `api/app/Domain/Workshop/Exceptions/{NotServiceDay,NoShowBeforeStart,NeedsReschedule}.php` | README §5. |
| `api/app/Http/Controllers/Api/V1/{WorkshopActionController,WorkshopBoardController}.php` | Thin. |
| `api/app/Http/Resources/WorkshopBoardResource.php` | API §B2. |
| `api/app/Http/Requests/*` | API §B2. |
| `api/app/Providers/AppServiceProvider.php` (`boot()`) | Abilities `check-in` (advisor, foreman), `run-workshop` (foreman), `view-board` (staff). |
| `api/app/Http/Requests/DealershipAppointmentsRequest.php` | `contactRequired` filter (D-12.14). |
| `tests/Support/AssertsNoOverlap.php`, M-04.2 query | Allow `OVERRUN` displacement (D-12.9). |
| `web/src/staff/workshop/*`, `web/src/staff/appointments/*` | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

```php
// illustrative
interface ReassignerInterface {
    /** One extend neighbour, inside the extend transaction. INV-12.4, 12.7. */
    public function reassign(Appointment $neighbour, ResourceSnapshot $snapshotWithExtension, CarbonImmutable $now): ReassignmentResult; // REASSIGNED | CONTACT_REQUIRED
}
interface WorkshopServiceInterface {
    /** `ExtendAppointment`. */
    public function extend(User $actor, string $id, int $expectedVersion, int $minutes, CarbonImmutable $now): ExtendResult; // appointment + affected[]
}
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `Reassigner` | Extend neighbours; later absences/outages (FC-12.2) |
| `ResourceSnapshot::withBusy` | Extend; later "what-if" previews |
| `AppointmentWriter::displace` | Extend |

FCs: FC-12.1 (manual technician choice) → `Reassigner` takes an optional forced pair; FC-12.2 (absences, bay outages later) → an extra `BusyIntervalReader` source (F03 ADR-03.3) + a loop over `Reassigner`, one transaction per booking.

## B7. Algorithms

**Status actions**: `findVisibleTo` (via the `{appointment}` binding) → rule checks (D-12.2) → `TransitionAppointment(to, changes{checked_in_at|started_at|completed_at = now})`.

**Extend (`ExtendAppointment`)**, up to 2 attempts:
1. Read job X (`findVisibleTo`); status must be `CHECKED_IN`/`IN_PROGRESS`; `newEnd = end + minutes`; `ext = [end, newEnd)`.
2. Neighbours = bookings ≠ X with an active `TECHNICIAN` allocation on X's technician or `BAY` allocation on X's bay overlapping `ext`.
3. Snapshot(s) of the neighbours' days, `withBusy(TECHNICIAN, X.tech, ext)` and `withBusy(BAY, X.bay, ext)`.
4. Transaction: `updateVersioned(X, expected, end_at = newEnd)` → for each neighbour C (read version vC): pairs = `find(C's range, excludeAppointmentId = C, prefer C's tech/bay)`; pairs → `moveTo`-in-transaction(C, vC, same range, pairs, REASSIGNED, AppointmentReassigned); none → `displace(C, vC, OVERRUN)` → deactivate X's allocations → insert X's allocations for `[start, newEnd)` (same technician, bay, vehicle) → history `EXTENDED` → commit.
5. 412 on X → 412 to the caller (no retry). 412 or 23P01 on a neighbour or on X's insert → rollback; attempt 2 from step 1; still failing → 409 `NO_AVAILABILITY` `SLOT_TAKEN`.
6. 200 `{ appointment, affected: [{appointmentId, reference, outcome, technician?, bay?}] }` + `ETag`.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| Bad `minutes` | 400 | `VALIDATION_ERROR` |
| Missing `Idempotency-Key` | 400 | `IDEMPOTENCY_KEY_REQUIRED` |
| Wrong role (advisor extends, starts, completes) | 403 | `FORBIDDEN` |
| Booking not visible | 404 | `APPOINTMENT_NOT_FOUND` |
| Extend could not settle neighbours after a retry | 409 | `NO_AVAILABILITY` (`reason: SLOT_TAKEN`) |
| Old version | 412 | `APPOINTMENT_MODIFIED` |
| Wrong status order | 422 | `INVALID_STATUS_TRANSITION` |
| Check-in on another day | 422 | `NOT_SERVICE_DAY` |
| No-show before start | 422 | `NO_SHOW_BEFORE_START` |
| Check-in of a booking displaced by an overrun | 422 | `NEEDS_RESCHEDULE` |
| Missing `If-Match` | 428 | `PRECONDITION_REQUIRED` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-12.1 | A `CONFIRMED` booking | Check in, start, complete | Status ends `COMPLETED`; allocations are inactive | TC-12-01 |
| AC-12.2 | A `COMPLETED` booking | Check in | 422 `INVALID_STATUS_TRANSITION` | TC-12-02 |
| AC-12.3 | (Requirement AC about technician absence — out of scope, D-12.5.) | — | — | — |
| AC-12.4 | A job in Bay 1 runs late; the next booking in Bay 1 starts right after | Extend by 30 minutes | The extension is saved; the next booking is reassigned or listed for contact | TC-12-04, TC-12-09 |
| AC-12.5 (new) | A booking tomorrow | Check in today | 422 `NOT_SERVICE_DAY` | TC-12-05 |
| AC-12.6 (new) | A booking starting in 30 minutes | Mark no-show | 422 `NO_SHOW_BEFORE_START`; after the start → 200, allocations inactive | TC-12-06 |
| AC-12.7 (new) | An `IN_PROGRESS` job with no neighbour | Extend 30 | 200, `end` +30 min, allocations extended | TC-12-08 |
| AC-12.8 (new) | The neighbour changed by someone else while extending | Extend | The extend retries once with fresh data and succeeds, or returns 409; never partial | TC-12-10 |
| AC-12.12 (new) | Board for today | `GET workshop-board` | Bays with blocks, technicians with level and booked minutes, an overrun alert for an `IN_PROGRESS` job past its end | TC-12-16 |
| AC-12.14 (new) | Booking Y was displaced by an overrun (no allocations) | The advisor checks it in | 422 `NEEDS_RESCHEDULE`; nothing changes; after a reschedule (same time allowed) check-in works | TC-12-27 |
| AC-12.15 (new) | The board shows an overrun alert for X at version 3; X is then completed (v4) | The foreman taps "Extend 30 min" | The request carries `If-Match: "3"` → 412 with the latest X; nothing extended | TC-12-28 |

## B10. Implementation order

1. Ticket 01 (prefactor): `ResourceSnapshot::withBusy` + `AppointmentWriter::displace`; nothing users can see.
2. Tracer bullet: check-in/start/complete/no-show endpoints + buttons on details, then `/code-review`.
3. Extend + reassignment + displacement + board alert.
4. Workshop board + contact list.
5. Acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `AssertsNoOverlap` after every test; M-04.2 (with the D-12.9 exception) returns 0 rows.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-12.2 | Extend locks several rows in one transaction → deadlocks with a parallel extend of a neighbour | Lock in a fixed order (job first, then neighbours by id); retry once (D-12.4) |
| R-12.3 | A displaced booking with no allocations confuses other features | D-12.9 documented; F05/F09/F07 handle `contactRequiredReason`; F09 `moveTo` inserts fresh allocations; check-in refused (D-12.2) |
