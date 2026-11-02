# F05 · View appointments · Web spec (WEB)

---

# PART A · For readers

| Screen | Path | Draft |
| --- | --- | --- |
| Appointments (staff) | `/staff/appointments` | `Admin/04-appointment-list.png` |
| Appointment details (staff) | `/staff/appointments/:id` | `Admin/05-appointment-details.png` |
| My bookings (customer) | `/my-bookings` | `Customer/05-my-bookings.png` |
| Details (customer) | `/my-bookings/:id` | `Customer/06-details-and-cancel.png` |

```
Appointments                                              [New booking]
Monday, 2 Nov 2026 · times in dealership time zone (GMT+7)
Date [02/11/2026]  Technician [All ▾]  Bay [All ▾]  Status [All ▾]
Time         Customer · Vehicle               Service     Technician    Bay    Status
09:00–10:00  Nguyễn Văn Hùng · Toyota Vios   Oil change  Trần Minh An  Bay 2  Confirmed  View
…
Showing the first 8 appointments, sorted by start time        [Load more]
```

```
Appointments / APT-20261102-0042
Nguyễn Văn Hùng · Toyota Vios   [Confirmed]
Monday, 2 Nov 2026, 09:00–10:00 · Oil change
[ action buttons: F07, F09, F12 ]
┌ Appointment info ───────────┐ ┌ Customer and vehicle ──────────────┐
│ Time  09:00–10:00, Mon 2 Nov│ │ Customer  Nguyễn Văn Hùng           │
│ Service Oil change · 60 min │ │ Phone  0903 *** 218  Show number    │
│ Technician Trần Minh An     │ │ Vehicle Toyota Vios 2021 · 51H-123.45│
│ Bay  Bay 2 · Quick service  │ │ VIN  RL4BT3F3X0M001234              │
│ Dealership Saigon Auto · D7 │ └─────────────────────────────────────┘
│ Created by Minh Anh, 28/10… │ ┌ History ───────────────────────────┐
└─────────────────────────────┘ │ • Booked · Minh Anh · 28/10 16:40   │
                                └─────────────────────────────────────┘
```

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Routes

| Path | Element | Guard |
| --- | --- | --- |
| `/staff/appointments` | `staff/appointments/AppointmentListPage` | every staff role |
| `/staff/appointments/:id` | `staff/appointments/AppointmentDetailsPage` | every staff role |
| `/my-bookings` | `customer/bookings/MyBookingsPage` | `CUSTOMER` |
| `/my-bookings/:id` | `customer/bookings/BookingDetailsPage` | `CUSTOMER` |

A 404 from the API → a "We couldn't find this appointment." page with a button back to the list.

## B2. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-05.1 | `appointmentQuery(id)` | `web/src/shared/api/appointmentQuery.ts` | `queryKey ['appointment', id]`; data `{ appointment, etag }` (`etag` read from the `ETag` header). `setAppointment(qc, appointment, etag)` is used by every mutation and by 412 (`AppointmentModifiedAlert.onReview`). `withIfMatch(etag)` → header. |
| WEB-05.2 | `AppointmentDetailsLayout` | `web/src/staff/appointments/AppointmentDetailsLayout.tsx` | Props: `appointment`, `actions` (ReactNode), `banner`; reused by F07, F09, F11, F12. |
| WEB-05.3 | `useDealershipAppointments(filters)` | `web/src/shared/api/appointments.ts` | `useInfiniteQuery`, `getNextPageParam = meta.nextCursor`; `refetchInterval` 60 s. |
| WEB-05.4 | `useMyAppointments(scope)` | same | `useInfiniteQuery`. |
| WEB-05.5 | `useAppointmentHistory(id)` | same | Only called for staff. |
| WEB-05.6 | `isClosed(status)` | `web/src/shared/appointments/status.ts` | `COMPLETED`, `CANCELLED`, `NO_SHOW`, `EXPIRED`. |

## B3. Behaviour

**Appointments**
- Filters live in the URL (`?date=2026-11-02&technicianId=…&status=CONFIRMED`) so links can be shared and F5 keeps them. `date` defaults to today in the dealership's time zone (not the browser's).
- Technician/Bay `Select` from `useTechnicians`, `useBays` (F01); "All" by default. Status `Select` has the 8 statuses + "All".
- Time column "09:00–10:00"; Customer · Vehicle on two lines (name bold; "{make} {model} · {plate}" dimmed); Status = `AppointmentStatusBadge`; Actions = a "View" link.
- Footer: "Showing the first {n} appointments, sorted by start time" (n = rows shown); "Load more" while `nextCursor` exists.
- Empty: "No appointments on this day."
- "New booking" button for advisors only.

**Appointment details (staff)**
- Breadcrumb "Appointments / {reference}"; title "{fullName} · {make} {model}"; badge; line "{dayLong}, {range} · {service}".
- Banner when `isClosed`: "This appointment is closed and can no longer be changed. Its bay and technician have been released for this time slot."
- Appointment info: Time, Service "{name} · {duration} min", Technician, Bay "{name} · {bayTypeLabel}", Dealership, Created by "{name}, {dd/MM/yyyy HH:mm}" (dealership time).
- Customer and vehicle: Customer, Phone (`maskPhone` + "Show number" reveals the full number as a `tel:` link), Vehicle "{make} {model} {year} · {plate}", VIN (row hidden when absent).
- History: a Timeline, each row "{label} · {actor name or 'System'} · {dd/MM HH:mm}"; label by `type` (B4).

**My bookings (customer)**
- Title "My bookings"; "Signed in as {email}" + "Sign out".
- `SegmentedControl` Upcoming | Past (kept in the URL as `?tab=past`).
- Card: "{dayShort} · {range}" + badge; service name; "{make} {model} · {dealership name} {branchName}"; last line "{reference}" + "View details" link.
- Empty: Upcoming "You have no upcoming bookings." + "New booking" button; Past "No past bookings yet."
- Bottom bar (draft): "New booking" (`/book`), "My bookings".

**Details (customer)**
- "Appointment details", reference; "{dayShort} · {range}" + badge; "{service} · about {duration} min"; Vehicle; "Booked by {fullName}"; "Reminder SMS 1 day before" when `reminderOptIn`; Location "{name} · {branchName}" + phone (`tel:` link).
- Actions area: F07 (Cancel), F09 (Change time); always "New booking".

**Links added to F02 (D-05.8)**: "View appointment" on the staff confirmed page; "View existing booking" in the `VEHICLE_ALREADY_BOOKED` card (`/staff/appointments/{existingAppointment.id}`; customers → `/my-bookings/{id}`); "View my bookings" on the customer confirmed page; "My bookings" in the customer header; "Check appointment list" in `IdempotentRetryPanel` (staff → `/staff/appointments?date=…`; customers → `/my-bookings`).

## B4. Copy

| Key | Copy |
| --- | --- |
| `list.title` | Appointments |
| `list.subtitle` | {dayLong} · times in dealership time zone ({offset}) |
| `list.filters.*` | Date · Technician · Bay · Status · All |
| `list.columns.*` | Time · Customer · Vehicle · Service · Technician · Bay · Status · Actions |
| `list.view` | View |
| `list.footer` | Showing the first {n} appointments, sorted by start time |
| `list.loadMore` | Load more |
| `list.empty` | No appointments on this day. |
| `details.closed` | This appointment is closed and can no longer be changed. Its bay and technician have been released for this time slot. |
| `details.info` | Appointment info |
| `details.customerVehicle` | Customer and vehicle |
| `details.fields.*` | Time · Service · Technician · Bay · Dealership · Created by · Customer · Phone · Vehicle · VIN |
| `details.showNumber` | Show number |
| `details.history` | History |
| `details.system` | System |
| `history.CREATED` | Booked |
| `history.HELD` | Slot held |
| `history.CONFIRMED` | Confirmed |
| `history.CANCELLED` | Cancelled |
| `history.RESCHEDULED` | Time changed |
| `history.EXPIRED` | Hold expired |
| `history.CHECKED_IN` | Checked in |
| `history.STARTED` | Work started |
| `history.COMPLETED` | Completed |
| `history.NO_SHOW` | Marked no-show |
| `history.EXTENDED` | Time extended |
| `history.REASSIGNED` | Technician or bay changed |
| `history.CONTACT_REQUIRED` | Customer contact needed |
| `notFound` | We couldn't find this appointment. |
| `my.title` | My bookings |
| `my.signedInAs` | Signed in as {email} |
| `my.upcoming` / `my.past` | Upcoming / Past |
| `my.viewDetails` | View details |
| `my.emptyUpcoming` | You have no upcoming bookings. |
| `my.emptyPast` | No past bookings yet. |
| `my.newBooking` | New booking |
| `cdetails.title` | Appointment details |
| `cdetails.about` | {service} · about {duration} min |
| `cdetails.bookedBy` | Booked by {name} |
| `cdetails.reminder` | Reminder SMS 1 day before |
| `cdetails.location` | Location |
| `viewAppointment` | View appointment |
| `viewExisting` | View existing booking |
| `viewMyBookings` | View my bookings |
| `checkList` | Check appointment list |
