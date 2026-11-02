# F02 · Create a booking · Web spec (WEB)

---

# PART A · For readers

## Staff web (1440 px)

**New booking** (`/staff`, draft `Admin/01-new-booking.png`): title "Book a service appointment", a subtitle, 3 numbered cards on the left (1. Customer and vehicle, 2. Service, 3. Date and time), a "Summary" card and the "Confirm booking" button on the right. Errors show right above the confirm button (draft `03-booking-error-states.png`).

```
Book a service appointment
The system checks a service bay and a qualified technician together for the full service duration.
┌ 1. Customer and vehicle ───────────────┐  ┌ Summary ───────────────────────────┐
│ Customer [Nguyễn Văn Hùng · 0903 *** 218▾]│ │ Customer   Nguyễn Văn Hùng          │
│ Vehicle  [Toyota Vios · 51H-123.45     ▾]│ │ Vehicle    Toyota Vios · 51H-123.45 │
│ Only vehicles owned by the selected …   │  │ Service    Oil change               │
├ 2. Service ────────────────────────────┤  │ Duration   60 min                   │
│ (•) Oil change   60 min · Quick service │  │ Time       Mon 2 Nov, 09:00–10:00   │
│ ( ) 20,000 km service 120 min · Standard│  │ Technician, bay  Assigned           │
│ ( ) EV battery check 90 min · EV        │  │   automatically on confirm          │
├ 3. Date and time ──────────────────────┤  │ [ error card if any ]               │
│ [Monday, 2 Nov 2026]                     │  │ [      Confirm booking      ]       │
│ Morning   08:00 08:15 08:30 …            │  │ Each booking carries its own …      │
│ Afternoon 13:00 13:15 …                  │  └─────────────────────────────────────┘
└─────────────────────────────────────────┘
```

**Appointment confirmed** (`/staff/bookings/:id/confirmed`, draft `02-booking-confirmed.png`): "Confirmed" badge, reference, details, SMS note, "Book another" button.

## Customer app (390 px)

| Step | Path | Draft |
| --- | --- | --- |
| 1 Choose a service | `/book` | `Customer/01-choose-service.png` |
| 2 Choose date and time | `/book/time` | `Customer/02-choose-date-time.png` |
| 3 Contact details | `/book/details` | `Customer/03-temporary-hold.png` (no countdown yet) |
| Booking confirmed | `/book/confirmed/:id` | `Customer/04-booking-confirmed.png` |

Deviations from the drafts: RQ §A3.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Routes

| Path | Element | Guard |
| --- | --- | --- |
| `/staff` (index) | `staff/booking/NewBookingPage` | `RequireRole ['SERVICE_ADVISOR']`; other staff roles → their role home (D-00.11) |
| `/staff/bookings/:id/confirmed` | `staff/booking/BookingConfirmedPage` | Same; reads the booking from `location.state`; no state → `/staff` |
| `/book` | `customer/booking/ChooseServicePage` | `RequireRole ['CUSTOMER']` |
| `/book/time` | `customer/booking/ChooseTimePage` | Same; no service in the draft → `/book` |
| `/book/details` | `customer/booking/ContactDetailsPage` | Same; no time → `/book/time` |
| `/book/confirmed/:id` | `customer/booking/BookingConfirmedPage` | Same; reads from `location.state`; none → `/book` |

## B2. Seams

| ID | Seam | File | Behaviour |
| --- | --- | --- | --- |
| WEB-02.1 | `formatInZone(iso, timeZone, pattern)` | `web/src/shared/format/time.ts` | `Intl.DateTimeFormat('en-GB', { timeZone, … })`; patterns: `time` "09:00", `range` "09:00–10:00", `dayLong` "Monday, 2 Nov 2026", `dayShort` "Mon 2 Nov". Never the browser's zone (`CLAUDE.md`). |
| WEB-02.2 | `localSlotsForDay(date, openingHours, durationMinutes, step=15)` | `web/src/shared/format/slots.ts` | Start times `HH:mm` from opening to `closes - duration`; returns ISO with the dealership's offset. Pure, unit tested. |
| WEB-02.3 | `maskPhone(phone)` | `web/src/shared/format/phone.ts` | `0903123218` → `0903 *** 218` (D-02.13). |
| WEB-02.4 | `AppointmentStatusBadge` | `web/src/shared/appointments/AppointmentStatusBadge.tsx` | Colour by status: HELD yellow, CONFIRMED blue, CHECKED_IN cyan, IN_PROGRESS indigo, COMPLETED green, CANCELLED gray, NO_SHOW red, EXPIRED gray. Labels from `CONTEXT.md`. |
| WEB-02.5 | `TimeGrid` | `web/src/shared/booking/TimeGrid.tsx` | Groups "Morning" (< 12:00) / "Afternoon"; single-select chips; past times `disabled`. Props let F06 pass per-chip state. |
| WEB-02.6 | `BookingErrorCard` | `web/src/shared/booking/BookingErrorCard.tsx` | Takes a `Problem` → the draft's card for `NO_AVAILABILITY` (heading + F03 reason copy), `VEHICLE_ALREADY_BOOKED`, `OUTSIDE_OPENING_HOURS`, `START_IN_PAST`, `START_TOO_FAR_AHEAD`; an `actions` slot for F05/F06/F09 buttons. |
| WEB-02.7 | `useCreateAppointment()` | `web/src/shared/api/appointments.ts` | Mutation using `idempotentPost` (F04) + `useIdempotencyKey(body)`; every answer goes through `settle()` (README D-G.15), so a retry after a 409/422 uses a new key. |
| WEB-02.8 | `BookingDraft` | `web/src/customer/booking/BookingDraftContext.tsx` | `{ dealershipId, serviceTypeId, date, start, vehicleId?, newVehicle?, reminderOptIn }` stored in `sessionStorage` (wrapped in `try/catch`); cleared after confirming. |
| WEB-02.9 | API hooks | `web/src/shared/api/customers.ts`, `vehicles.ts` | `useCustomerSearch(query)` (300 ms debounce, only with ≥ 3 characters), `useCustomerVehicles(id)`, `useMyVehicles()`, `useAddVehicle()`. |

## B3. Behaviour — New booking (staff)

- **Customer**: searchable Mantine `Select` fed by `useCustomerSearch`; each row "{fullName} · {maskPhone(phone)}". When `meta.total` > 20, the last row says "Showing 20 of {total} matches. Type more to narrow the search." Changing the customer clears the vehicle.
- **Vehicle**: `Select` from `useCustomerVehicles`; rows "{make} {model} · {licencePlate}"; note "Only vehicles owned by the selected customer are shown." A `VEHICLE_NOT_OWNED_BY_CUSTOMER` error shows right under this field (draft: field-level), not as a card.
- **Service**: `Radio.Card` from `useServiceTypes` (active only); meta "{duration} · {bayTypeLabel}".
- **Date**: `TextInput type="date"` (no new package, README D-G.11) plus ‹ › buttons; shows "Monday, 2 Nov 2026"; min today, max today + 90 ("today" is the dealership's local date, from `formatInZone`, never the browser's); a closed day → "The dealership is closed on this day." and an empty grid.
- **Time**: `TimeGrid` with `localSlotsForDay`; under it "Times in dealership time zone (GMT+7)." (offset computed with `Intl`, never hard-coded).
- **Summary**: live; "Technician, bay — Assigned automatically on confirm".
- **Confirm booking**: `SubmitButton` ("Confirming…"); `disabled` with "Choose a time to continue" when data is missing; `doubleSubmitNote` (F04) below it.
- Success → `navigate('/staff/bookings/:id/confirmed', { state: appointment })`, `reset()` the key.
- Errors: `BookingErrorCard` above the button; no-response / `REQUEST_IN_PROGRESS` → `IdempotentRetryPanel` (staff variant; the "Check appointment list" button appears once F05 has the page).

**Appointment confirmed**: "Appointment confirmed" + badge; "Appointment ID {reference}. The bay and technician are reserved for the full service duration."; Time / Service / Technician / Bay / Customer / Vehicle; SMS note; "Book another" (back to `/staff`, form cleared).

## B4. Behaviour — customer flow

**Step 1**: header "Saigon Auto" + "{branchName} branch"; title "Book a service"; "Step 1 of 3 · Choose a service"; one `Radio.Card` per service: name + "About {duration} · {description}" (`duration`: < 120 minutes → "{n} min"; a multiple of 60 and ≥ 120 → "{n/60} hours"); line "Can't find what you need? Call {phone} and a service advisor will book it for you." (`phone` is a `tel:` link); sticky footer "Selected {name}" + "Choose date and time" (`disabled` until chosen). D-02.12: a "Dealership" field when there is more than 1 dealership.

**Step 2**: "Step 2 of 3 · Choose date and time"; line "Service {name} · {duration}" + "Change" link; month name; chips for the next 14 days (closed days `disabled`, horizontal scroll); `TimeGrid` for the chosen day; footer "Your choice {dayShort}, {time}" + "Continue".

**Step 3**: "Step 3 of 3 · Contact details"; "Your selection" card (Service, Time, Dealership); "Full name", "Phone number" read-only from `useMe().customer`; "Vehicle": `Radio` with saved vehicles + "Add a new vehicle" (opens Licence plate, Make, Model; Year optional); `Checkbox` "Text me a reminder 1 day before" (on by default); "Confirm appointment".
- With a new vehicle: call `useAddVehicle` first (its own key), then create the booking. A 409 plate error → error on the Licence plate field. If the vehicle was saved but the booking failed, the vehicle stays saved and is preselected when the user retries.
- 409 `NO_AVAILABILITY` → back to step 2 with an `Alert` (F03 copy, README §5). 422 `START_IN_PAST` → same. `VEHICLE_ALREADY_BOOKED` → `Alert` above the button.

**Booking confirmed**: check icon, "Booking confirmed", "We'll send a confirmation SMS to {maskPhone}."; card: Appointment ID, Time ("Monday, 2 Nov 2026" / "10:00–11:00"), Service, Vehicle, Location ("{name} · {branchName}"); "Please arrive 10 minutes early. You can change or cancel it under My bookings." Button "Book another service" (to `/book`). F05 adds "View my bookings".

## B5. Copy

| Key | Copy |
| --- | --- |
| `staff.title` | Book a service appointment |
| `staff.subtitle` | The system checks a service bay and a qualified technician together for the full service duration. |
| `staff.section1` | 1. Customer and vehicle |
| `staff.customer` / `staff.vehicle` | Customer / Vehicle |
| `staff.customerPlaceholder` | Search by name, phone or email |
| `staff.searchMore` | Showing 20 of {total} matches. Type more to narrow the search. |
| `staff.vehicleNote` | Only vehicles owned by the selected customer are shown. |
| `staff.section2` | 2. Service |
| `staff.section3` | 3. Date and time |
| `staff.zoneNote` | Times in dealership time zone ({offset}). |
| `closedDay` | The dealership is closed on this day. |
| `morning` / `afternoon` | Morning / Afternoon |
| `summary.*` | Summary · Customer · Vehicle · Service · Duration · Time |
| `summary.assigned` | Technician, bay — Assigned automatically on confirm |
| `confirm` | Confirm booking |
| `chooseTime` | Choose a time to continue |
| `confirmed.title` | Appointment confirmed |
| `confirmed.body` | Appointment ID {reference}. The bay and technician are reserved for the full service duration. |
| `confirmed.sms` | A confirmation SMS will reach the customer within minutes. The appointment does not depend on the message being delivered. |
| `bookAnother` | Book another |
| `err.outsideHours.title` | The service would end after closing time |
| `err.outsideHours.body` | A {service} takes {duration} minutes. Starting at {start} it would end at {end}, after the {closesAt} closing time. The latest start that day is {latestStart}. |
| `err.vehicleBooked.title` | Vehicle {plate} already has a booking at {range} that day |
| `err.vehicleBooked.body` | A vehicle cannot be in two bays at once. You can reschedule the existing booking instead of creating a new one. |
| `err.notOwned` | This vehicle does not belong to {customer}. Choose another vehicle or change the customer. |
| `err.pickAnother` | Pick another time |
| `cust.brandBranch` | {branchName} branch |
| `cust.title` | Book a service |
| `cust.step1` | Step 1 of 3 · Choose a service |
| `cust.about` | About {duration} · {description} |
| `cust.call` | Can't find what you need? Call {phone} and a service advisor will book it for you. |
| `cust.selected` | Selected {name} |
| `cust.toTime` | Choose date and time |
| `cust.step2` | Step 2 of 3 · Choose date and time |
| `cust.change` | Change |
| `cust.yourChoice` | Your choice {summary} |
| `cust.continue` | Continue |
| `cust.step3` | Step 3 of 3 · Contact details |
| `cust.yourSelection` | Your selection |
| `cust.fullName` / `cust.phone` | Full name / Phone number |
| `cust.vehicle` | Vehicle |
| `cust.addVehicle` | Add a new vehicle |
| `cust.plate` / `cust.make` / `cust.model` / `cust.year` | Licence plate / Make / Model / Year (optional) |
| `cust.reminder` | Text me a reminder 1 day before |
| `cust.confirm` | Confirm appointment |
| `cust.confirmed.title` | Booking confirmed |
| `cust.confirmed.sms` | We'll send a confirmation SMS to {phone}. |
| `cust.confirmed.arrive` | Please arrive 10 minutes early. You can change or cancel it under My bookings. |
| `cust.bookAnother` | Book another service |
| `cust.location` | Location |
| `cust.appointmentId` | Appointment ID |

## B6. Accessibility and widths

- Customer: 390 px first; touch targets ≥ 44 px; the sticky footer never covers content (bottom padding = footer height).
- `TimeGrid`: chips are `button`s with `aria-pressed`; disabled times have `aria-disabled` and the tooltip "This time has passed".
- Error cards: `role="alert"`, focus moves to the card when it appears.
