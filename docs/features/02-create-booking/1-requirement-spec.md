# F02 · Create a booking · Requirement spec (RQ)

Branch: `feature/02-create-booking` · Priority: Must · Shared conventions: [README](../README.md)

---

# PART A · For readers

## A1. Story

**Why this feature exists.** This is the system's main job: a service advisor (at the desk) or a customer (in the app) books one service for one vehicle at one time, and immediately gets a **firm** appointment with a technician and a bay.

**The problem.** Today bookings come from the desk and the app at the same time; users do not know whether a booking is firm, or what to do when there is no slot.

**What it does.**
- Advisor: picks the customer (search by name/phone/email), one of that customer's vehicles, the service, date and time → "Confirm booking" → "Appointment confirmed" screen.
- Customer: 3 steps on the phone: choose a service → choose date and time → contact details and vehicle (pick a saved vehicle or add a new one) → "Confirm appointment" → "Booking confirmed".
- The system assigns the technician and bay (F03), saves safely (F04), and writes the confirmation message to the outbox.
- When it fails: a clear reason and what to do next (time in the past, after closing, vehicle not the customer's, vehicle already booked, no slot).

**Who uses it, where.** Advisor: staff web → New booking (`/staff`). Customer: customer app → Book a service (`/book`).

**Inputs.** Customer (picked by the advisor; for customers, themselves), vehicle, service type, desired start time.

**Outcome.** A `CONFIRMED` appointment with an `APT-…` reference, a technician, a bay, and end time = start + duration.

**What it does not do.** The struck-through free-time grid and alternatives (F06 adds them to the same screens), the 5-minute hold (F10 adds it to the customer flow), advisors creating customers or adding vehicles for them.

## A2. Usage steps

**Advisor** (`docs/designs/Admin/screenshots/01-new-booking.png`, `02-booking-confirmed.png`, `03-booking-error-states.png`)
1. Type ≥ 3 characters of a name, phone or email → pick the customer.
2. Pick a vehicle (only that customer's).
3. Pick a service.
4. Pick a date, then a time (15-minute steps within opening hours).
5. Check the "Summary" → "Confirm booking" → "Appointment confirmed".

**Customer** (`docs/designs/Customer/screenshots/01-choose-service.png`, `02-choose-date-time.png`, `03-temporary-hold.png`, `04-booking-confirmed.png`)
1. Pick a service → "Choose date and time".
2. Pick a date and time → "Continue".
3. Check name and phone (from the account), pick a vehicle or add a new one, choose whether to get an SMS reminder → "Confirm appointment".
4. "Booking confirmed" screen.

## A3. Screens and deviations from the drafts

| Draft | F02 spec | Reason |
| --- | --- | --- |
| Time grid with struck-through slots, "{n} free slots", "This day is fully booked" | F02: every time within opening hours is selectable (past times disabled). F06 adds free/taken state | Build order; F06 owns it |
| Customer step 2: "Hold and continue" + "We hold this slot for you for 5 minutes." | F02: "Continue", no hold. F10 restores the draft | FR-02.6 + build order (D-02.2) |
| Customer step 3: countdown, "Slot held for you" | F02: no countdown; title "Contact details" | Same |
| Customer step 3: only a "Licence plate" field | Pick a saved vehicle, or add a new one with Licence plate + Make + Model | FR-02.2 |
| Customer step 3: name and phone are inputs | Shown read-only from the account | FR-10.2; profile editing is out of scope |
| Customer "Booking confirmed": "We sent a confirmation SMS to…" | "We'll send a confirmation SMS to {phone}." | The message sits in the outbox, sent later (F11) |
| Customer "Booking confirmed": "Add to calendar", "Directions" | Removed | Out of scope; the dealership has no address yet |
| Staff `VEHICLE_ALREADY_BOOKED` error: "View existing booking", "Reschedule existing booking" | F02 shows the error card without buttons; F05 adds "View existing booking", F09 adds "Reschedule existing booking" | Target pages do not exist yet |
| Staff "Booking confirmed": "View appointment" | F05 adds this button | The details page is F05 |
| `NO_AVAILABILITY` error: "Nearest available slots" | F06 adds them | F06 owns them |

## A4. Key rules in plain words

- The start time must be in the future, on a 15-minute step, within 90 days.
- The whole range `[start, end)` must fit within the opening hours of **one** day, in the dealership's local time.
- One vehicle cannot have two overlapping bookings (a car cannot be in two bays).
- Customers book only for themselves and their own vehicles; a customer id in the body is ignored.
- Advisors book only vehicles that belong to the customer they picked.

## A5. Out of scope

Payments, pricing, parts; advisors creating new customers; several services in one booking; advisors booking for another dealership.

## A6. Success criteria

- NFR-02: `POST /appointments` p95 < 300 ms with 20 bays and 30 technicians (measured by TC-02-30).
- An advisor finishes a booking in ≤ 6 clicks after picking the customer (measured by hand).
- No double booking (AC-04.1 rerun through the real endpoint).

## A7. Decisions

| ID | Decision |
| --- | --- |
| D-02.1 | The start field is named `desiredStart` (FR-02.1). |
| D-02.2 | Field `mode`: F02 only accepts `CONFIRM` (the default when missing). F10 adds `HOLD` and switches the customer web flow to hold first (FR-02.6). Before F10, customers book `CONFIRM` directly. |
| D-02.3 | Advisor: the dealership is always the advisor's; `dealershipId` in the body is ignored. Customer: `dealershipId` is required. |
| D-02.4 | Only `SERVICE_ADVISOR` and `CUSTOMER` create bookings. Foreman, admin → 403 (README §4). |
| D-02.5 | A customer using a vehicle that is not theirs → 404 `VEHICLE_NOT_FOUND` (does not reveal the vehicle exists). An advisor using another customer's vehicle → 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER` (AC-02.4). |
| D-02.6 | Extra time rules: 15-minute step (wrong → 400 `VALIDATION_ERROR`), at most 90 days ahead (wrong → 422 `START_TOO_FAR_AHEAD`, new code). |
| D-02.7 | Check order: format, including the 15-minute step (400, FormRequest, in the dealership's zone) → service type (404) → customer (404) → vehicle (404/422) → past (422) → too far (422) → opening hours (422) → F03 (409) → write (409/201). The step needs the dealership's time zone, so the FormRequest resolves the dealership first (advisor: own; customer: `dealershipId`, missing → 404 `DEALERSHIP_NOT_FOUND`). |
| D-02.15 | Personal data: every advisor of every dealership can search every customer (README D-G.9) and sees full name, email and phone (needed to book and call). Search is capped (20 results, ≥ 3 characters, 60/minute per advisor), never logged with its `query`, and returns `meta.total` so advisors narrow the search instead of paging. Customer rows are kept while the account exists (no delete feature). |
| D-02.8 | Extra endpoints: `GET /customers?query=` (advisor customer search, ≥ 3 characters, ≤ 20 results), `GET /customers/{id}/vehicles` (advisor), `GET /me/vehicles`, `POST /me/vehicles` (customer). |
| D-02.9 | A customer's new vehicle: `licencePlate`, `make`, `model` required; `year`, `vin` optional. A plate that already exists (anyone's) → 409 `PLATE_ALREADY_REGISTERED`; an existing VIN → 409 `VIN_ALREADY_REGISTERED`. At most 10 vehicles per customer → 422 `VEHICLE_LIMIT_REACHED` (new code). |
| D-02.10 | Customer search matches `full_name` (case-insensitive, contains), phone (digits, starts with), email (starts with). Uses the `pg_trgm` extension (PostgreSQL contrib, not a package) for the name index. |
| D-02.11 | The customer screens keep the booking draft (dealership, service, date, time, vehicle) in `sessionStorage` so F5 does not lose it; cleared after confirming. |
| D-02.12 | When a customer has several dealerships to choose from, step 1 shows a "Dealership" field; with only 1, it is preselected and hidden. |
| D-02.13 | Phone numbers display masked in the middle: `0903 *** 218` (first 4 digits, last 3). Staff screens have "Show number". |
| D-02.14 | After creation, a `CONFIRMED` booking writes `AppointmentConfirmed` to the outbox (F04 `AppointmentWriter`); F02 never calls SMS. |

## A8. Open questions

| ID | Question | Default |
| --- | --- | --- |
| Q-02.a | Can advisors book for a customer without an account? | No (in this version every customer has an account, D-00.3). |
| Q-02.b | Can someone book later today, close to now (for example in 15 minutes)? | Yes, as long as it is in the future and within opening hours. |
| Q-02.c | Limit on a customer's open bookings? | None in this version. |

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Current code state

Assumes F00, F01, F03, F04 are merged: `CurrentCustomer`, Gates, `DealershipAccess`, `MasterDataRepository`, `CandidateFinder`, `OpeningHours`, `AllocationSnapshotRepository`, `AppointmentWriter`, `AppointmentResource`, `AppointmentResponder`, middleware `idempotent`, web `useIdempotencyKey`, `SubmitButton`, `IdempotentRetryPanel`, `errorCopy`, `useMe`, `RequireRole`, `masterData.ts`. Route `/staff` is the empty `StaffHomePage` and `/` is `CustomerHomePage`.

## B2. Scope

| ID | In scope |
| --- | --- |
| S-02.1 | `POST /api/v1/appointments` (`CONFIRM`). |
| S-02.2 | `GET /customers`, `GET /customers/{customer}/vehicles`, `GET /me/vehicles`, `POST /me/vehicles`. |
| S-02.3 | Seam `BookingTimeRules` (step, past, 90 days, opening hours). |
| S-02.4 | `BookingServiceInterface::create()` — the operation the specs call `CreateBooking` (the D-02.7 checks + F03 + F04), README §6.3. |
| S-02.5 | Migration: `pg_trgm` + customer search indexes. |
| S-02.6 | Staff web: New booking, Booking confirmed, error cards. |
| S-02.7 | Customer web: Choose a service, Choose date and time, Contact details, Booking confirmed. |
| S-02.8 | Web seams: `formatInZone`, `maskPhone`, `AppointmentStatusBadge`, a basic `TimeGrid` (F06 upgrades it to `SlotGrid`). |

## B3. Invariants

| ID | Invariant | Where and when it is read |
| --- | --- | --- |
| INV-02.1 | Customer path: the booking's `customer_id` = the signed-in user's customer (`CurrentCustomer`), ignoring any `customerId` in the body (BR-00.2, AC-02.5). | In `CreateBooking`, before any other read. |
| INV-02.2 | The vehicle must belong to the booking's customer. `vehicles.customer_id` is read once before finding pairs; the DB has no cross FK for this, so the app checks it. A change of owner between read and write: there is no change-owner feature in this version. | `CreateBooking` step 4. |
| INV-02.3 | `end = desiredStart + service_types.duration_minutes` (FR-02.3), from the service row read in the same request; the service must be `active` and in the same dealership. | `CreateBooking` step 2. |
| INV-02.4 | The whole `[start, end)` fits within the opening hours of the local day containing `start` (BR-02.2), via `OpeningHours::fits` with the dealership's time zone. | `BookingTimeRules`. |
| INV-02.5 | `start > now` by the app clock (BR-02.1, README D-G.6); `start <= now + 90 days`; the local minute is divisible by 15 and seconds = 0. | `BookingTimeRules`. |
| INV-02.6 | No booking is created if any check fails; errors follow the D-02.7 order (the first failing check wins). | `CreateBooking`. |
| INV-02.7 | Every booking created through this endpoint has 3 active allocations and exactly 1 `AppointmentConfirmed` outbox event (F04). | `AppointmentWriter`. |
| INV-02.8 | The 201 response has `Location`, `ETag: "1"`, and an `AppointmentResource` body with `status: CONFIRMED` (FR-02.5). | `AppointmentResponder::created`. |
| INV-02.9 | Advisors can find and use any customer (customers do not belong to a dealership, README D-G.9), but the booking always belongs to the advisor's dealership (D-02.3). | `CreateBooking`, `CustomerSearch`. |
| INV-02.10 | `POST /me/vehicles`: `customer_id` from `CurrentCustomer`; `plate_key` and `vin` unique (F01); a duplicate from parallel requests → map `23505` to the right 409 code. | `AddVehicle`. |

Edge cases:

| Decision | Edge case | TC |
| --- | --- | --- |
| Who books for whom (INV-02.1, 02.2, D-02.5) | Body carries another `customerId`; another customer's vehicle (advisor); another customer's vehicle (customer); vehicle does not exist | TC-02-05, TC-02-04, TC-02-10, TC-02-11 |
| Time (INV-02.4, 02.5) | Past; exactly `now`; 16:30 + 60 minutes; Sunday; 16:00 + 60 minutes (exact boundary); no offset; 09:10; 91 days | TC-02-02, 02-03, 02-12, 02-13, 02-14, 02-15, 02-16 |
| Service (INV-02.3) | Missing; inactive; another dealership's | TC-02-06, TC-02-17 |
| Roles (D-02.4) | Foreman, admin | TC-02-18 |
| New vehicle (INV-02.10) | Same plate spelled differently; duplicate VIN; 11th vehicle | TC-02-09, TC-02-24 |

## B4. File-by-file changes

| File | Change |
| --- | --- |
| `api/app/Domain/Booking/BookingTimeRules.php` | `STEP_MINUTES = 15`, `MAX_DAYS_AHEAD = 90`; `check(CarbonImmutable $start, int $minutes, OpeningHours $hours, CarbonImmutable $now): TimeRange` throws per D-02.7. |
| `api/app/Domain/Booking/Services/BookingService(.php, Interface.php)` | `create()` = B7 (`CreateBooking`); bound in `DomainServiceProvider`. |
| `api/app/Domain/Booking/Exceptions/{StartInPast,StartTooFarAhead,OutsideOpeningHours,VehicleNotOwnedByCustomer,ServiceTypeNotFound,CustomerNotFound,VehicleNotFound}.php` | README §5. |
| `api/app/Domain/Customers/Services/CustomerService(.php, Interface.php)` (`search` = `CustomerSearch`, `vehiclesOf`, `addVehicle` = `AddVehicle`), `api/app/Domain/Customers/Repositories/CustomerRepository(.php, Interface.php)`, exceptions `PlateAlreadyRegistered`, `VinAlreadyRegistered`, `VehicleLimitReached` | D-02.8–02.10, D-02.15. |
| `api/app/Http/Requests/CreateAppointmentRequest.php`, `AddVehicleRequest.php`, `CustomerSearchRequest.php` | API §B2. |
| `api/app/Http/Controllers/Api/V1/{Appointment,Customer,MyVehicle}Controller.php` | Thin; call `BookingServiceInterface`, `CustomerServiceInterface` only. |
| `api/app/Http/Resources/{CustomerSummary,Vehicle}Resource.php` | API §B2. |
| `api/routes/api.php` | 5 routes; gates `create-appointment`, `search-customers`, `customer`. |
| `api/app/Providers/AppServiceProvider.php` (`boot()`) | Abilities `create-appointment` (advisor, customer), `search-customers` (advisor). |
| `api/database/migrations/2026_11_06_000001_add_customer_search_indexes.php` | DB. |
| `web/src/staff/booking/*`, `web/src/customer/booking/*`, `web/src/shared/format/*`, `web/src/shared/api/appointments.ts`, `vehicles.ts`, `customers.ts` | WEB. |

## B5. Contracts

[4-api-spec.md](4-api-spec.md), [3-database-spec.md](3-database-spec.md), [5-web-spec.md](5-web-spec.md).

`CreateBooking` (_illustrative_, serves INV-02.1–02.7):

```php
interface BookingServiceInterface {
    /** The operation the specs call `CreateBooking`. */
    public function create(User $actor, CreateBookingInput $in, CarbonImmutable $now): Appointment;
}
// CreateBookingInput: dealershipId?, customerId?, vehicleId, serviceTypeId, desiredStart (CarbonImmutable with its original offset), reminderOptIn
```

## B6. Seams

| Seam | Reused in |
| --- | --- |
| `BookingTimeRules` | F06 (time grid), F09 (reschedule) |
| `CreateBooking` | F10 (adds the `HOLD` branch) |
| Web `formatInZone`, `maskPhone`, `AppointmentStatusBadge` | F05, F07, F09, F10, F12 |
| Web `BookingDraft` (context + sessionStorage) | F06, F10 |
| Web `TimeGrid` | Upgraded to `SlotGrid` by F06 |
| Web `BookingErrorCard` (maps `code` → error card) | F06 (adds alternatives), F09 |

FCs: FC-02.1 (several services per booking) → `CreateBookingInput` holds one service, change later; FC-02.2 (advisor creates a customer) → an empty `CustomerSearch` result leaves room for a "New customer" button.

## B7. Algorithm (`CreateBooking`)

1. Dealership: advisor → `actor.dealership_id`; customer → `dealershipId` (missing → 404 `DEALERSHIP_NOT_FOUND`).
2. Read the service by `(dealershipId, serviceTypeId, active)` → none → 404 `SERVICE_TYPE_NOT_FOUND`.
3. Customer: customer → `CurrentCustomer`; advisor → `customers.id = customerId` → none → 404 `CUSTOMER_NOT_FOUND`.
4. Read the vehicle by `vehicleId` → none → 404 `VEHICLE_NOT_FOUND`. `vehicle.customer_id ≠ customer.id`: customer → 404 `VEHICLE_NOT_FOUND`; advisor → 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER`.
5. `BookingTimeRules::check(desiredStart, duration, openingHours, now)` → `TimeRange` (UTC).
6. Snapshot of the local day (`AllocationSnapshotRepository::forDay`) → `CandidateFinder::find` → no pair → 409 `NO_AVAILABILITY` with F03's `reason`.
7. `AppointmentWriter::create(NewAppointment{status: CONFIRMED, source, createdBy, reminderOptIn}, pairs, now)` → 201 / 409.

## B8. Error table

| Situation | HTTP | `code` |
| --- | --- | --- |
| Missing field, wrong type, `desiredStart` without offset, not on a 15-minute step, `mode` other than `CONFIRM` | 400 | `VALIDATION_ERROR` |
| Missing `Idempotency-Key` | 400 | `IDEMPOTENCY_KEY_REQUIRED` |
| Foreman/admin books; customer searches customers | 403 | `FORBIDDEN` |
| Dealership (sent by a customer) missing | 404 | `DEALERSHIP_NOT_FOUND` |
| Service missing/inactive/another dealership | 404 | `SERVICE_TYPE_NOT_FOUND` |
| Customer (sent by an advisor) missing | 404 | `CUSTOMER_NOT_FOUND` |
| Vehicle missing; someone else's vehicle (sent by a customer) | 404 | `VEHICLE_NOT_FOUND` |
| No pair left | 409 | `NO_AVAILABILITY` |
| Vehicle already has an overlapping booking | 409 | `VEHICLE_ALREADY_BOOKED` |
| Plate / VIN already exists | 409 | `PLATE_ALREADY_REGISTERED` / `VIN_ALREADY_REGISTERED` |
| Time in the past | 422 | `START_IN_PAST` |
| More than 90 days ahead | 422 | `START_TOO_FAR_AHEAD` |
| Outside opening hours | 422 | `OUTSIDE_OPENING_HOURS` |
| Vehicle not the customer's (sent by an advisor) | 422 | `VEHICLE_NOT_OWNED_BY_CUSTOMER` |
| Customer already has 10 vehicles | 422 | `VEHICLE_LIMIT_REACHED` |

## B9. Acceptance criteria

| AC | Given | When | Then | TC |
| --- | --- | --- | --- | --- |
| AC-02.1 | A qualified technician and a matching bay are free 09:00–10:00 | Advisor books an oil change (60 min) at 09:00 | 201, `CONFIRMED`, technician and bay assigned, row saved | TC-02-01 |
| AC-02.2 | It is now 10:00 | Book 09:00 the same day | 422 `START_IN_PAST` | TC-02-02 |
| AC-02.3 | The dealership closes at 17:00 | Book a 60-minute service at 16:30 | 422 `OUTSIDE_OPENING_HOURS` | TC-02-03 |
| AC-02.4 | Vehicle v-002 belongs to another customer | Advisor books with customer c-001 and vehicle v-002 | 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER` | TC-02-04 |
| AC-02.5 | Customer A is signed in | Send a body with customer B's id | The body's customer id is ignored; the booking is for A | TC-02-05 |
| AC-02.6 | Service type st-x does not exist | Book with st-x | 404 `SERVICE_TYPE_NOT_FOUND` | TC-02-06 |
| AC-02.7 | The vehicle is booked 09:00–10:00 | Book the same vehicle at 09:30 | 409 `VEHICLE_ALREADY_BOOKED` | TC-02-07 |
| AC-02.8 | No `Idempotency-Key` header | Send the request | 400 `IDEMPOTENCY_KEY_REQUIRED` | TC-02-08 |
| AC-02.9 (new) | Customer | Add vehicle `51G-999.99`; add `51g 99999` again | 201; then 409 `PLATE_ALREADY_REGISTERED` | TC-02-09 |
| AC-02.10 (new) | Customer B's vehicle | Customer A books with it | 404 `VEHICLE_NOT_FOUND` | TC-02-10 |
| AC-02.11 (new) | — | `desiredStart` = `2026-11-02T09:10:00+07:00`; `2026-11-02T09:00:00` (no offset) | 400 `VALIDATION_ERROR` for both | TC-02-12 |
| AC-02.12 (new) | — | Book 91 days ahead | 422 `START_TOO_FAR_AHEAD` | TC-02-15 |
| AC-02.13 (new) | Closed on Sunday | Book Sunday 09:00 | 422 `OUTSIDE_OPENING_HOURS` | TC-02-13 |
| AC-02.14 (new) | Foreman, admin | `POST /appointments` | 403 `FORBIDDEN` | TC-02-18 |
| AC-02.15 (new) | Advisor | `GET /customers?query=hu`; `?query=hùng`; a customer calls it | 400; list has Nguyễn Văn Hùng; 403 | TC-02-19 |
| AC-02.16 (new) | — | A successful booking through the endpoint | 1 `AppointmentConfirmed` event in the outbox (AC-11.1) | TC-02-22 |
| AC-02.17 (new) | — | A successful booking | `Location`, `ETag: "1"`, body with every FR-02.5 field | TC-02-01 |
| AC-02.18 (new) | New booking screen | Pick customer, vehicle, service, time → Confirm | "Appointment confirmed" screen with the `APT-…` reference, times in the dealership's zone | TC-02-25 |
| AC-02.19 (new) | New booking screen | Server returns 422 `OUTSIDE_OPENING_HOURS` | Card "The service would end after closing time" with the latest start | TC-02-26 |
| AC-02.20 (new) | Customer | Goes through 3 steps, adds a new vehicle, confirms | "Booking confirmed" with the right reference and time | TC-02-27 |
| AC-02.21 (new) | Only 1 pair | 20 parallel requests through the real `POST /appointments` | 1 × 201, 19 × 409 (AC-04.1 through the real endpoint) | TC-02-20 |

## B10. Implementation order

1. Ticket 01 (prefactor): `BookingTimeRules` split out of F03 `OpeningHours` + tests; web `formatInZone`, `maskPhone`; nothing users can see.
2. Tracer bullet: `POST /appointments` (advisor) + minimal New booking + Confirmed, then `/code-review`.
3. Full D-02.7 checks + error cards.
4. Customer search, customer vehicles.
5. Customer flow + my vehicles.
6. Performance + acceptance.

## B11. Definition of Done

Every TC green; README §8 green; `AssertsNoOverlap` in every booking test; M-02.1 measured once and recorded in the BA.

## B12. Technical risks

| ID | Risk | Mitigation |
| --- | --- | --- |
| R-02.1 | `Carbon::parse` accepts `desiredStart` without an offset (uses the app zone) | Regex requiring the offset in the FormRequest before parsing (TC-02-12) |
| R-02.2 | Computing the local date from UTC → wrong day for early-morning bookings | F03 `OpeningHours` + TC-02-14 |
| R-02.3 | Loading a day snapshot per request slows p95 | Fixed query count (F03 TC-03-19); TC-02-30 measures |
| R-02.4 | The customer screens lose the draft on reload | D-02.11 |
