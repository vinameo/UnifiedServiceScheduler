# F07 · Cancel an appointment · Requirement spec (RQ)

Branch: `feature/07-cancel-appointment` · Priority: Should · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** When a customer says they cannot come, the slot must be released **at once** so someone else can book it. Customers also want to cancel in the app themselves.

**What it does.**
- An advisor cancels a booking from the details page and must pick a reason (an optional note too).
- A customer cancels their own booking in the app, reason optional, only while at least 2 hours remain before the start; closer than that, the app shows the dealership's phone number.
- Cancelling releases the technician, bay and vehicle in the same save, and writes the cancellation message to the outbox.
- Cancelling an already-cancelled booking returns its current state, without an error.

**Who uses it.** Advisors (web, Appointment details), customers (app, Details).

**What it does not do.** Cancellation fees, restoring a cancelled booking, bulk cancellation.

## A2. Usage steps

**Advisor**: Appointment details → "Cancel appointment" → dialog "Cancel this appointment?" → pick a reason, add a note → "Cancel appointment" → the page shows the closed banner and status Cancelled.

**Customer**: My bookings → View details → "Cancel appointment" → bottom sheet "Cancel your 10:00 appointment on Mon 2 Nov?" → pick a reason (optional) → "Cancel appointment" → banner "You cancelled this appointment".

## A3. Screens and deviations from the drafts

Drafts: `Admin/05-appointment-details.png` (cancel dialog), `Customer/06-details-and-cancel.png`.

| Draft | Spec | Reason |
| --- | --- | --- |
| Customer: "A cancellation SMS is on its way." | "We'll send you a cancellation SMS." | The message sits in the outbox; F11 sends it |
| The staff dialog names the bay and technician | Kept, taken from the booking | — |

## A4. Key rules in plain words

- Only **Confirmed** or **On hold** bookings can be cancelled. Checked-in, in-progress and finished bookings cannot.
- Customers cancel only their own bookings, and only while ≥ 2 hours remain before the start. Advisors have no time limit.
- A cancel is based on the latest version: if someone else just changed the booking, the person cancelling sees the new version and decides again.

## A5. Out of scope

Cancellation fees, per-service cancel policies, cancellations by foremen/admins.

## A6. Success criteria

- After a cancel, the slot can be booked again at once (AC-07.1).
- No cancelled booking keeps active allocations (M-04.2 = 0).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-07.1 | Advisor reasons (required): `CUSTOMER_REQUEST`, `CUSTOMER_BUSY`, `DONE_ELSEWHERE`, `DEALERSHIP_ISSUE`, `OTHER`. Customer reasons (optional): `SOMETHING_CAME_UP`, `DONE_ELSEWHERE`, `OTHER`. Note ≤ 500 characters, optional for both. |
| D-07.2 | Only advisors and customers cancel (README §4); foreman, admin → 403. |
| D-07.3 | The 2-hour limit (BR-07.2) applies to customers on `CONFIRMED` bookings: allowed while `start − now ≥ 2 hours`. A customer cancelling a `HELD` has no limit. |
| D-07.4 | Cancelling a `CANCELLED` booking → 200 with the current state, nothing written, no version bump (FR-07.2), even with an old `If-Match`. `If-Match` is still required (428 when missing). |
| D-07.5 | If the versioned update hits 0 rows and a fresh read shows the booking is now `CANCELLED` (someone just cancelled it) → 200 with the current state (what the user wanted already happened); any other status → 412. |
| D-07.6 | Outbox `AppointmentCancelled` only when cancelling from `CONFIRMED`; cancelling a hold sends nothing. |
| D-07.7 | F07 is the first change endpoint over real HTTP, so it proves F04's 412 and 428 again on a real endpoint. |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-07.a | Does the advisor's `OTHER` reason require a note? | No. |
| Q-07.b | Do customers see the reason an advisor recorded? | No; they only see "Cancelled by the dealership". |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00–F06 are merged: `TransitionAppointment`, `AppointmentStatus::canMoveTo`, middleware `idempotent`, `if-match`, `ExpectedVersion`, `AppointmentModified` (412 with latest data), `findVisibleTo`, `OutboxWriter`, `AppointmentResponder`, web `appointmentQuery`, `AppointmentDetailsLayout`, `AppointmentModifiedAlert`, `SubmitButton`, `useIdempotencyKey`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-07.1 | `POST /api/v1/appointments/{appointment}/cancel`. |
| S-07.2 | Action `CancelAppointment`; enum `CancelReason`. |
| S-07.3 | Web: cancel dialog (staff), cancel sheet (customer), banners after cancelling. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-07.1 | Cancel sets `status = CANCELLED` and every allocation `active = false` in **one** transaction (FR-07.1), through `TransitionAppointment` (F04 INV-04.5). | `TransitionAppointment`'s transaction. |
| INV-07.2 | Only `CONFIRMED`, `HELD` → `CANCELLED` (BR-07.1); anything else → 422 `INVALID_STATUS_TRANSITION` with `currentStatus`. | `canMoveTo` on the status read through `findVisibleTo`; 412 beats 422 when the version is old (F04 TC-04-26). |
| INV-07.3 | Customers: `start_at − now ≥ 2 hours` for `CONFIRMED`; `now` from the app clock; `start_at` from the same read as the status. | `CancelAppointment` before the transaction. A parallel reschedule (F09) makes `start_at` stale → the versioned `UPDATE` hits 0 rows → 412, so the 2-hour rule cannot be bypassed. |
| INV-07.4 | The person cancelling must be able to see the booking (`findVisibleTo`); a customer cancelling someone else's → 404. | First step. |
| INV-07.5 | Cancelling a cancelled booking writes nothing (D-07.4); a clash with another canceller returns 200 (D-07.5). A 412 for another status is never retried (BR-04.2). | `CancelAppointment`. |
| INV-07.6 | `cancel_reason`, `cancel_note`, `cancelled_at`, `cancelled_by_user_id` are written in the same version-incrementing `UPDATE`. | `changes` passed to `TransitionAppointment`. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Status (INV-07.2) | `COMPLETED`, `CHECKED_IN`, `IN_PROGRESS`, `NO_SHOW`, `EXPIRED`, `HELD` | TC-07-03, TC-07-12 |
| 2 hours (INV-07.3) | 1 hour left; exactly 2 hours; 1 h 59 min; advisor with 30 minutes left; customer cancels a `HELD` close to the start | TC-07-04, TC-07-05, TC-07-13 |
| Duplicate cancel (INV-07.5) | Cancel again after cancelling; two people cancel at once; same key resent | TC-07-02, TC-07-09, TC-07-14 |
| Permission (INV-07.4, D-07.2) | Another customer; foreman; staff of another dealership | TC-07-07, TC-07-08 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Booking/CancelReason.php` | Enum + `forStaff()`, `forCustomer()`. |
| `api/app/Domain/Booking/Actions/CancelAppointment.php` | B7. |
| `api/app/Domain/Booking/Exceptions/TooLateToCancel.php` | 422 `TOO_LATE_TO_CANCEL`, member `dealershipPhone`. |
| `api/app/Http/Requests/CancelAppointmentRequest.php` | API §B2. |
| `api/app/Http/Controllers/Api/V1/AppointmentController.php` | Add `cancel`. |
| `api/app/Providers/AuthServiceProvider.php` | Ability `cancel-appointment` (advisor, customer). |
| `api/routes/api.php` | Route with `auth:sanctum`, `can:cancel-appointment`, `idempotent`, `if-match`. |
| `api/app/Http/Resources/AppointmentResource.php` | `cancellation` (F04 API §B2); customers get `byRole` but not the staff `reason`/`note` (Q-07.b). |
| `web/src/staff/appointments/CancelAppointmentModal.tsx`, `web/src/customer/bookings/CancelBookingSheet.tsx`, `web/src/shared/api/appointments.ts` (`useCancelAppointment`) | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [5-web-spec.md](5-web-spec.md). No new tables ([3-database-spec.md](3-database-spec.md)).

```php
// illustrative
final class CancelAppointment {
    public function __invoke(User $actor, string $appointmentId, int $expectedVersion, ?CancelReason $reason, ?string $note, CarbonImmutable $now): Appointment;
}
```

## B6. Seams

Uses: `findVisibleTo`, `TransitionAppointment`, `ExpectedVersion`, web `appointmentQuery`, `AppointmentDetailsLayout`, `AppointmentModifiedAlert`.
New: `isWithinCustomerChangeWindow(appointment, now)` (2 hours) in `api/app/Domain/Booking/CustomerChangePolicy.php` and `web/src/shared/appointments/changeWindow.ts` — reused by F09 for reschedule.

## B7. Algorithm (`CancelAppointment`)

1. `appointment = findVisibleTo(actor, id)` (404).
2. `status == CANCELLED` → return 200 with the current booking (D-07.4).
3. Role: advisors need `reason` ∈ `forStaff()` (checked by the FormRequest, 400); customers `reason` ∈ `forCustomer()` or null.
4. Customer + `CONFIRMED` + `start − now < 2 hours` → 422 `TOO_LATE_TO_CANCEL` (`dealershipPhone`).
5. `TransitionAppointment(id, expected, CANCELLED, changes{cancel_reason, cancel_note, cancelled_at = now, cancelled_by_user_id}, outbox = was CONFIRMED ? AppointmentCancelled : null, actor, now)`.
   - `InvalidStatusTransition` → 422.
   - `AppointmentModified` → read again; `CANCELLED` → 200 with the new version (D-07.5); otherwise rethrow (412).
6. 200 `AppointmentResource` + new `ETag`.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| Missing `reason` (advisor); `reason` not in the role's list; `note` > 500 | 400 | `VALIDATION_ERROR` |
| Missing `Idempotency-Key` | 400 | `IDEMPOTENCY_KEY_REQUIRED` |
| Foreman, admin | 403 | `FORBIDDEN` |
| Booking not visible | 404 | `APPOINTMENT_NOT_FOUND` |
| Old version (and not already cancelled) | 412 | `APPOINTMENT_MODIFIED` |
| Status cannot be cancelled | 422 | `INVALID_STATUS_TRANSITION` |
| Customer cancels too close to the start | 422 | `TOO_LATE_TO_CANCEL` |
| Missing `If-Match` | 428 | `PRECONDITION_REQUIRED` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-07.1 | Booking A at 09:00 uses the only technician | Cancel A, then book B at 09:00 | Cancel returns 200; B gets 201 | TC-07-01 |
| AC-07.2 | The booking is already `CANCELLED` | Cancel again | 200, still `CANCELLED`, version unchanged | TC-07-02 |
| AC-07.3 | The booking is `COMPLETED` | Cancel | 422 `INVALID_STATUS_TRANSITION` | TC-07-03 |
| AC-07.4 | A customer's booking starts in 1 hour | The customer cancels in the app | 422 `TOO_LATE_TO_CANCEL` | TC-07-04 |
| AC-07.5 (new) | A booking starts in 30 minutes | An advisor cancels | 200 | TC-07-05 |
| AC-07.6 (new) | — | An advisor cancels without `reason` | 400 `VALIDATION_ERROR` | TC-07-06 |
| AC-07.7 (new) | Customer B's booking | Customer A cancels it | 404 `APPOINTMENT_NOT_FOUND` | TC-07-07 |
| AC-07.8 (new) | A foreman | Cancels | 403 `FORBIDDEN` | TC-07-08 |
| AC-07.9 (new) | 5 parallel cancel requests (different keys, same `If-Match`) | Run them | All 5 get 200 `CANCELLED`; version up by exactly 1; 1 history row; 1 outbox event | TC-07-09 |
| AC-07.10 (new) | A `CONFIRMED` booking; a `HELD` booking | Cancel each | `CONFIRMED`: 1 `AppointmentCancelled` event; `HELD`: none | TC-07-10 |
| AC-07.11 (new) | Booking v2 | Cancel with `If-Match: "1"`; without `If-Match` | 412 with `appointment` + `ETag: "2"`; 428 | TC-07-11 |
| AC-07.12 (new) | Staff cancels on the web | Click "Cancel appointment" | Dialog with the right copy; after confirming: Cancelled badge + closed banner; action buttons hidden | TC-07-15 |
| AC-07.13 (new) | Customer, booking 1 hour away | Opens details | No cancel button; "It's less than 2 hours…" with the dealership's number | TC-07-16 |

## B10. Implementation order

1. Ticket 01 (prefactor): `CustomerChangePolicy` (+ web version) split out; nothing users can see.
2. Tracer bullet: endpoint + staff dialog, then `/code-review`.
3. Customer rules + customer sheet.
4. Concurrency + acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `AssertsNoOverlap` after every test.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-07.1 | The 2-hour check runs on stale data | INV-07.3: the versioned update blocks it |
| R-07.2 | Returning 200 on a duplicate cancel hides a real error | Only when the fresh status is `CANCELLED` (D-07.5) |
