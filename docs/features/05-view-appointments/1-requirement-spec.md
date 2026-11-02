# F05 · View appointments · Requirement spec (RQ)

Branch: `feature/05-view-appointments` · Priority: Must · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** Advisors and foremen need to know which car goes to which bay with whom today. Customers need to see their upcoming and past bookings.

**What it does.**
- Staff: a day list of appointments (filter by technician, bay, status), sorted by start time, and a details page for one appointment (info, customer and vehicle, change history).
- Customers: "My bookings" with Upcoming and Past tabs, and a details page for their own booking.
- The details page is where later features attach action buttons (cancel F07, reschedule F09, check-in F12).

**Who uses it, where.** All staff: web → Appointments. Customers: app → My bookings.

**Outcome.** Data is always read from the DB (survives restarts), times show in the dealership's time zone, and every appointment comes with an `ETag` so later changes can send `If-Match`.

**What it does not do.** Searching by customer name or plate, exports, a timeline view (the Workshop board is F12).

## A2. Usage steps

1. Staff → Appointments → today by default → change the date/filters → "View" a row → details page.
2. Customer → My bookings → Upcoming (earliest first) or Past (latest first) → "View details".

## A3. Screens and deviations from the drafts

Drafts: `Admin/04-appointment-list.png`, `Admin/05-appointment-details.png`, `Customer/05-my-bookings.png`, `Customer/06-details-and-cancel.png`.

| Draft | Spec | Reason |
| --- | --- | --- |
| Check in, Reschedule, Cancel, Mark no-show… buttons on the details page | F05 builds an empty actions area; F07, F09, F12 add the buttons | Build order |
| "Technician: Trần Minh An · Basic skill" | "Trần Minh An" | The appointment API does not return skills |
| "View notification log" | Added by F11 | F11 owns it |
| Customer "Signed in as 0903 *** 218" | "Signed in as {email}" | Q-00.c |
| Customer details: Cancel / Change time buttons | Added by F07 / F09 | Build order |

## A4. Key rules in plain words

- Customers only see their own bookings. Opening someone else's → "We couldn't find this appointment" (it does not say it exists).
- Staff only see their own dealership's bookings.
- The "date" filter is the dealership's local date: a 06:30 booking at UTC+7 belongs to that day even though it is 23:30 the day before in UTC.
- Upcoming: active bookings that have not ended. Past: completed, cancelled, no-show, or past their end time. Holds and expired holds are never shown.

## A5. Out of scope

Free-text search, print, CSV export, push notifications when a booking changes.

## A6. Success criteria

- A day list (≤ 200 bookings) returns in under 200 ms p95.
- There is no way for a customer to see another customer's booking (TC-05-04, TC-05-05).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-05.1 | Staff list: `date` is required (`YYYY-MM-DD`, local date); a booking belongs to the date when `start_at` is within local `[00:00, 24:00)`. |
| D-05.2 | The staff list shows every status except `EXPIRED` by default; `status` accepts several values (`status=CONFIRMED,CHECKED_IN`). |
| D-05.3 | Upcoming = `status ∈ {CONFIRMED, CHECKED_IN, IN_PROGRESS}` and `end_at > now`, by `start_at` ascending. Past = `status ∈ {COMPLETED, CANCELLED, NO_SHOW}` or (`status ∈ {CONFIRMED, CHECKED_IN, IN_PROGRESS}` and `end_at <= now`), by `start_at` descending. `HELD` and `EXPIRED` are never shown to customers. |
| D-05.4 | History (`appointment_events`) is staff only: `GET /appointments/{id}/history`. |
| D-05.5 | Every staff role (advisor, foreman, admin) can read the list and details (README §4). |
| D-05.6 | Cursor = base64url of `start_at|id` (D-G.12); `limit` 1–200, default 50. A bad cursor → 400. |
| D-05.7 | The staff details page shows a masked phone + "Show number" (D-02.13). |
| D-05.8 | F05 adds the links F02 is missing: "View appointment", "View existing booking", "View my bookings", "My bookings", "Check appointment list". |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-05.a | Does the staff list refresh itself? | Yes, when returning to the tab (`refetchOnWindowFocus`) and every 60 seconds. |
| Q-05.b | Can customers see the change history? | No (D-05.4). |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00–F04 and F02 are merged: `AppointmentRepository` (`find`), `AppointmentResource`, `AppointmentResponder`, `appointment_events`, route binding `{dealership}`, web `formatInZone`, `AppointmentStatusBadge`, `maskPhone`, `BookingErrorCard` (`actions` slot), the F02 confirmed pages.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-05.1 | `GET /appointments/{appointment}`, `GET /appointments/{appointment}/history`, `GET /dealerships/{dealership}/appointments`, `GET /me/appointments`. |
| S-05.2 | Seam `AppointmentRepository::findVisibleTo(User, id)` + exception `AppointmentNotFound` (404) + `AppointmentPolicy`. |
| S-05.3 | `AppointmentListItemResource`, `AppointmentEventResource`, cursor helper `CursorPaginator`. |
| S-05.4 | Web: Appointment list, Appointment details (staff); My bookings, Details (customer); `appointmentQuery` keeping the `ETag`. |
| S-05.5 | Web: the D-05.8 links. |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-05.1 | Customers read only bookings with `customer_id` = their customer; staff read only bookings with `dealership_id` = their dealership. Out of scope → 404 `APPOINTMENT_NOT_FOUND`, identical to a missing booking (BR-00.2, BR-00.3, AC-00.8). | `findVisibleTo` puts the scope in the **query** (`WHERE id = ? AND customer_id = ?`), never read-then-compare. |
| INV-05.2 | `GET /appointments/{id}` returns `ETag` = the `version` of exactly the row it read (FR-05.1). | `AppointmentResponder::ok`. |
| INV-05.3 | `date` is read in `dealerships.timezone`; the local window `[00:00, 24:00)` is converted to UTC with Carbon (DST-safe). | List controller. |
| INV-05.4 | Lists have a stable order: staff `(start_at ASC, id ASC)`; Upcoming `(start_at ASC, id ASC)`; Past `(start_at DESC, id DESC)`. The cursor uses exactly that key, so paging never duplicates or skips. | `CursorPaginator`. |
| INV-05.5 | `limit` ∈ [1, 200]; default 50 (FR-05.4). | FormRequest. |
| INV-05.6 | `technicianId`, `bayId` filters of another dealership → empty list (no error, nothing revealed). | `dealership_id` is always in the query. |
| INV-05.7 | History contains no personal data (F04 DB-04.4); staff only. | Gate `staff` on the history route. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| INV-05.1 | Customer B reads A's booking; D2 staff read a D1 booking; missing id; malformed id | TC-05-04, 05-05, 05-03, 05-11 |
| INV-05.3 | 06:30 local = 23:30 UTC the day before | TC-05-02 |
| INV-05.4 | Two bookings with the same `start_at`; page 2 | TC-05-07 |
| D-05.3 | A past `CONFIRMED`; a `HELD`; an `EXPIRED` | TC-05-06 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Booking/Repositories/AppointmentRepository.php` | Add `findVisibleTo`, `listForDealershipDay`, `listForCustomer`, `history`. |
| `api/app/Domain/Booking/Exceptions/AppointmentNotFound.php` | 404 `APPOINTMENT_NOT_FOUND`. |
| `api/app/Policies/AppointmentPolicy.php` | `view` (used by `findVisibleTo`; never read first and `authorize` after). |
| `api/app/Support/Pagination/CursorPaginator.php` | Encode/decode cursors; 400 when bad. |
| `api/app/Http/Controllers/Api/V1/{AppointmentController (show, history), DealershipAppointmentController, MyAppointmentController}.php` | Thin. |
| `api/app/Http/Requests/{DealershipAppointmentsRequest,MyAppointmentsRequest}.php` | API §B2. |
| `api/app/Http/Resources/{AppointmentListItemResource,AppointmentEventResource}.php` | API §B2. |
| `api/routes/api.php` | 4 routes. |
| `web/src/shared/api/appointmentQuery.ts` | WEB-05.1. |
| `web/src/staff/appointments/*`, `web/src/customer/bookings/*` | WEB. |
| `web/src/staff/booking/*`, `web/src/customer/booking/*` | D-05.8 links. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [5-web-spec.md](5-web-spec.md). No new tables ([3-database-spec.md](3-database-spec.md)).

```php
// illustrative — INV-05.1
public function findVisibleTo(User $user, string $id): Appointment; // throws AppointmentNotFound
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `findVisibleTo` | F07, F09, F10, F12 (every change reads the appointment through it first) |
| `CursorPaginator` | F11 (outbox), F08 (recent errors) |
| Web `appointmentQuery` + `withIfMatch` + `setAppointment(queryClient, appointment, etag)` | F07, F09, F10, F12 |
| Web `AppointmentDetailsLayout` (`actions` area, banner) | F07, F09, F11, F12 |

## B7. Algorithms

**Staff list**: turn `date` into `[fromUtc, toUtc)`; `WHERE dealership_id = :d AND start_at >= :from AND start_at < :to AND status IN (:statuses)` (+ `technician_id`, `bay_id` when given) + the cursor condition `(start_at, id) > (:s, :id)`; `ORDER BY start_at, id LIMIT :limit + 1` (the extra row → `nextCursor`).

**My bookings**: per D-05.3, same cursor approach; `now` from the app clock.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| `date` missing/bad; unknown `status`; `limit` outside [1,200]; bad `cursor`; unknown `scope`; malformed filter id | 400 | `VALIDATION_ERROR` |
| No session | 401 | `UNAUTHENTICATED` |
| Customer calls the staff list or history | 403 | `FORBIDDEN` |
| Staff call `/me/appointments` | 403 | `FORBIDDEN` |
| Another dealership | 404 | `DEALERSHIP_NOT_FOUND` |
| Booking missing or out of scope | 404 | `APPOINTMENT_NOT_FOUND` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-05.1 | A booking was made, then the app was restarted | `GET /appointments/{id}` | Same data as when it was created | TC-05-01 |
| AC-05.2 | A UTC+7 dealership has a booking at 06:30 local (23:30 UTC the day before) | Filter by that local date | The booking is in the result | TC-05-02 |
| AC-05.3 | No booking with that id | `GET /appointments/{id}` | 404 `APPOINTMENT_NOT_FOUND` | TC-05-03 |
| AC-05.4 | Customer A has 2 bookings, customer B has 3 | A opens My bookings | A sees exactly 2 | TC-05-06 |
| AC-00.8 | Customer A is signed in | Opens customer B's booking | 404 `APPOINTMENT_NOT_FOUND` | TC-05-04 |
| AC-00.7 | Customer is signed in | `GET /dealerships/{id}/appointments` | 403 `FORBIDDEN` | TC-05-10 |
| AC-05.5 (new) | D2 staff | Opens a D1 booking | 404 `APPOINTMENT_NOT_FOUND` | TC-05-05 |
| AC-05.6 (new) | 3 bookings that day; filter by technician/bay/status | Call the list | Only matching rows, sorted by time | TC-05-08 |
| AC-05.7 (new) | 120 bookings that day | `limit=50`, following `nextCursor` | 50 + 50 + 20, no duplicates, nothing skipped | TC-05-07 |
| AC-05.8 (new) | A `CONFIRMED` booking yesterday, a `HELD` booking, a `CANCELLED` booking next week | Customer opens Upcoming, Past | Upcoming: none of these 3; Past: yesterday's and the cancelled one; no `HELD` | TC-05-06 |
| AC-05.9 (new) | Appointments screen | Change the date and filters | The URL follows; the table is right; "Load more" loads the next page | TC-05-14 |
| AC-05.10 (new) | Details page of a `CANCELLED` booking | Open it | Banner "This appointment is closed…"; history has the cancel row | TC-05-15 |

## B10. Implementation order

1. Ticket 01 (prefactor): `findVisibleTo` + `AppointmentNotFound` + `CursorPaginator`; nothing users can see.
2. Tracer bullet: `GET /appointments/{id}` + staff details page + "View appointment" link, then `/code-review`.
3. Staff list + screen.
4. My bookings + customer details.
5. History + remaining links; acceptance.

## B11. Definition of Done

Every TC green; README §8 green.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-05.1 | Implicit Eloquent route model binding for `{appointment}` skips the scope | No implicit binding for `{appointment}`; the controller calls `findVisibleTo` |
| R-05.2 | A cursor holding `start_at` as a string loses microsecond precision | Encode ISO with microseconds; TC-05-07 uses equal `start_at` values |
