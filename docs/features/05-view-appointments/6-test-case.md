# F05 · View appointments · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Who sees what**: HTTP tests on real PostgreSQL for every combination (own customer / other customer / staff of the same dealership / staff of another dealership).
- **Dates in the dealership's time zone** and **paging**: HTTP tests with chosen data (early-morning bookings, many bookings at the same time).
- **Screens**: component tests (filters in the URL, "Load more", banner, history, customer tabs).
- **One manual run**.

## A2. Manual scenarios

| # | Steps | Expected | Result, date, tester |
| --- | --- | --- | --- |
| M1 | Advisor → Appointments | Today's date; subtitle "times in dealership time zone (GMT+7)" | Passed (Automated in `ViewAppointmentsAcceptanceTest::test_TC_05_19_M1_*` & `AppointmentListPage.test.tsx`), 2026-11-03, Ticket 10 Acceptance |
| M2 | Filter Technician = Trần Minh An | Only An's bookings; the URL has `technicianId` | Passed (Automated in `ViewAppointmentsAcceptanceTest::test_TC_05_19_M2_*` & `AppointmentListPage.test.tsx`), 2026-11-03, Ticket 10 Acceptance |
| M3 | F5 | Filters kept | Passed (Automated in `ViewAppointmentsAcceptanceTest::test_TC_05_19_M3_*` & `AppointmentListPage.test.tsx`), 2026-11-03, Ticket 10 Acceptance |
| M4 | View a booking | Details page; "Show number" shows the full number; History has "Booked" | Passed (Automated in `ViewAppointmentsAcceptanceTest::test_TC_05_19_M4_*` & `AppointmentDetailsPage.test.tsx`), 2026-11-03, Ticket 10 Acceptance |
| M5 | Customer Hùng → My bookings | The new booking is in Upcoming; "Signed in as hung@example.test" | Passed (Automated in `ViewAppointmentsAcceptanceTest::test_TC_05_19_M5_*` & `MyBookingsPage.test.tsx`), 2026-11-03, Ticket 10 Acceptance |
| M6 | Customer Khánh pastes `/my-bookings/{Hùng's id}` | "We couldn't find this appointment." | Passed (Automated in `ViewAppointmentsAcceptanceTest::test_TC_05_19_M6_*` & `BookingDetailsPage.test.tsx`), 2026-11-03, Ticket 10 Acceptance |

## A3. Test data

`World::saigonD7()`, 2 customers, bookings made with `AppointmentFactory` (F04). Clock frozen at 2026-11-02 08:00 +07.

## A4. Not tested

Performance at large scale (M-05.1 is watched through F08).

## A5. Exit criteria

Every TC green; M1–M6 pass.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-05.1, FR-05.1, INV-05.2 | TC-05-01 |
| AC-05.2, FR-05.2, INV-05.3 | TC-05-02 |
| AC-05.3 | TC-05-03 |
| AC-05.4, AC-05.8, FR-05.3, D-05.3 | TC-05-06 |
| AC-00.8, INV-05.1 | TC-05-04 |
| AC-00.7 | TC-05-10 |
| AC-05.5, INV-05.1 | TC-05-05 |
| AC-05.6, INV-05.6 | TC-05-08, TC-05-09 |
| AC-05.7, FR-05.4, INV-05.4, INV-05.5 | TC-05-07 |
| AC-05.9 | TC-05-14 |
| AC-05.10 | TC-05-15 |
| D-05.2 | TC-05-08 |
| D-05.4, INV-05.7 | TC-05-13, TC-05-20 |
| D-05.9 | TC-05-20 |
| D-05.1 `meta.total` | TC-05-07 |
| D-05.6, API §B4 | TC-05-11 |
| D-05.7 | TC-05-15 |
| D-05.8 | TC-05-17 |
| WEB-05.1 | TC-05-16 |
| WEB-05.4 | TC-05-18 |
| DB §B2 (no N+1) | TC-05-12 |
| R-05.1 | TC-05-04, TC-05-05 |

## B2. Test cases

Backend in `api/tests/Feature/Booking/View/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-05-01 | AC-05.1, INV-05.2 | Integration | Book through `POST /appointments`, keep the body | `$this->refreshApplication()`; sign in again; `GET /appointments/{id}` | Body identical to the creation body; `ETag: "1"` | Data only in memory | `ShowAppointmentTest::test_AC_05_1_appointment_survives_restart` |
| TC-05-02 | AC-05.2, INV-05.3 | Integration | Booking with `start_at = 2026-11-01T23:30:00Z` (06:30 on the 2nd local; inserted by the factory, ignoring opening hours) | Advisor `?date=2026-11-02`; `?date=2026-11-01` | Present; absent | Filter by UTC date | `DealershipAppointmentsTest::test_AC_05_2_date_is_local_to_the_dealership` |
| TC-05-03 | AC-05.3 | Integration | — | `GET /appointments/{random uuid}` | 404 `APPOINTMENT_NOT_FOUND` | 500 | `ShowAppointmentTest::test_AC_05_3_unknown_id` |
| TC-05-04 | AC-00.8, INV-05.1 | Integration | Customer B's booking | Customer A `GET /appointments/{id}` | 404 `APPOINTMENT_NOT_FOUND`, body like TC-05-03 except `correlationId` | 403 or a visible difference | `…::test_AC_00_8_customer_cannot_see_another_customers_appointment` |
| TC-05-05 | AC-05.5, INV-05.1 | Integration | A D1 booking | D2 advisor `GET /appointments/{id}` | 404 `APPOINTMENT_NOT_FOUND` | Dealership not filtered | `…::test_AC_05_5_staff_of_another_dealership_gets_404` |
| TC-05-06 | AC-05.4, AC-05.8, D-05.3 | Integration | A: 2 future `CONFIRMED`, 1 `CONFIRMED` yesterday, 1 `CANCELLED` next week, 1 `HELD`, 1 `EXPIRED`; B: 3 bookings | A calls `scope=upcoming`, `scope=past` | Upcoming: exactly 2, earliest first; Past: yesterday's + the cancelled, latest first; no `HELD`/`EXPIRED`; none of B's | D-05.3 wrong | `MyAppointmentsTest::test_AC_05_4_and_05_8_upcoming_and_past` |
| TC-05-07 | AC-05.7, INV-05.4, INV-05.5 | Integration | 120 bookings that day, 10 with the same `start_at` | `limit=50` following `nextCursor` to the end | 50, 50, 20; the union is 120 distinct ids; order `(start, id)` ascending; last page `nextCursor: null`; `meta.total` = 120 on every page | Cursor only on `start_at` | `DealershipAppointmentsTest::test_AC_05_7_cursor_paging_has_no_gaps_or_duplicates` |
| TC-05-08 | AC-05.6, D-05.2 | Integration | 5 bookings: An/K1 CONFIRMED, Dũng/K2 CHECKED_IN, An/K2 CANCELLED, EXPIRED, Chi/K3 COMPLETED | Filter `technicianId=An`; `bayId=K2`; `status=CONFIRMED,CHECKED_IN`; no filter | 2; 2; 2; 4 (no EXPIRED) | Wrong filter | `…::test_AC_05_6_filters` |
| TC-05-09 | INV-05.6 | Integration | A technician of D2 | D1 advisor filters by that `technicianId` | 200, `data: []` | Error or D2 bookings leaked | `…::test_INV_05_6_foreign_filter_returns_nothing` |
| TC-05-10 | AC-00.7 | Integration | Customer | `GET /dealerships/{d}/appointments?date=…` | 403 `FORBIDDEN` | Gate missing | `…::test_AC_00_7_customer_cannot_list_staff_appointments` |
| TC-05-11 | D-05.6, API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `AppointmentListAdversarialInputTest` (data provider) |
| TC-05-12 | DB §B2 | Integration | 5 then 50 bookings | Count the list's queries | Equal | N+1 | `DealershipAppointmentsTest::test_query_count_is_constant` |
| TC-05-13 | D-05.4, INV-05.7 | Integration | A booking created then `touch`ed (F04 test route) | Advisor `GET /history`; the owning customer `GET /history` | 2 rows (`CREATED`, …) in time order, no phone/email; customer 403 | History open to customers | `AppointmentHistoryTest::test_D_05_4_history_is_staff_only` |
| TC-05-14 | AC-05.9 | Web component | Fake API with 2 pages | Render `/staff/appointments`, pick a Technician, click "Load more" | URL has `technicianId`; the request has `technicianId`; page 2 appended; footer "Showing 60 of 75 appointments…" (`meta.total` 75) | Filters not in the URL | `web/src/staff/appointments/AppointmentListPage.test.tsx` |
| TC-05-15 | AC-05.10, D-05.7 | Web component | Fake API: a `CANCELLED` booking + history | Render details; click "Show number" | Closed banner; History has "Cancelled · Minh Anh"; the full number shows | Closed not distinguished | `…/AppointmentDetailsPage.test.tsx` |
| TC-05-16 | WEB-05.1 | Web unit | — | `appointmentQuery` fetch with header `ETag: "3"`; `setAppointment(…, '"4"')` | Cache has `etag '"3"'` then `'"4"'`; `withIfMatch` returns `{ 'If-Match': '"4"' }` | ETag lost | `web/src/shared/api/appointmentQuery.test.ts` |
| TC-05-17 | D-05.8 | Web component | Staff confirmed page; `VEHICLE_ALREADY_BOOKED` card with `existingAppointment` | Render | "View appointment" → `/staff/appointments/{id}`; "View existing booking" → the right id | Link missing | `web/src/staff/booking/BookingConfirmedPage.test.tsx`, `BookingErrorCard.test.tsx` |
| TC-05-18 | WEB-05.4 | Web component | Fake API: Upcoming 1, Past 0 | Render `/my-bookings`, switch to Past | Card "Mon 2 Nov · 10:00–11:00" + "Confirmed"; Past: "No past bookings yet."; URL `?tab=past` | Tab not in the URL | `web/src/customer/bookings/MyBookingsPage.test.tsx` |
| TC-05-19 | All | Manual | Stack running | M1–M6 | As A2 | — | Recorded in A2 (`ViewAppointmentsAcceptanceTest`) |
| TC-05-20 | D-05.9, INV-05.1, INV-05.7 | Integration | A D1 booking; D2 advisor; every route whose URI contains `{appointment}` (read from `Route::getRoutes()`) | D2 advisor calls each route (GET, or POST with valid headers) | Every one: 404 `APPOINTMENT_NOT_FOUND`, nothing written | A route loads the appointment without the binding (IDOR) | `ShowAppointmentTest::test_D_05_9_every_appointment_route_is_scoped` |

## B3. Fixtures

- `AppointmentFactory` (F04) gains `->at('2026-11-01T23:30:00Z')->status('CANCELLED')->forCustomer($c)` (ignores opening hours, tests only).
- Web: `web/src/test/fixtures/appointment.ts` (F02) gains one variant per status.

## B4. Regression

F00–F04 and F02 tests; especially `CreateBookingTest`, `BookingFlow.test.tsx` (the new links must not change the flow).
