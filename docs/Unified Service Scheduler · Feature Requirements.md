# Unified Service Scheduler · Feature Requirements

## Overview

This document lists the requirements for the 12 features F01 to F12, plus **F00 Accounts and sign-in**, which every other feature depends on. Customers create their own account with email and password. Staff accounts are loaded from seed data, so there is no staff sign-up screen in this version.

**Roles**

| Role | Where they sign in | Where the account comes from |
| --- | --- | --- |
| Customer | Customer app, Sign-in screen | Customer app, Sign-up screen (email + password) |
| Service advisor | Staff web, Sign-in screen | Seed data (no sign-up screen yet) |
| Workshop foreman | Staff web, Sign-in screen | Seed data (no sign-up screen yet) |
| Admin | Staff web, Sign-in screen | Seed data (no sign-up screen yet) |

**Feature map**

| ID | Feature | Priority | Who can use it | Screens |
| --- | --- | --- | --- | --- |
| F00 | Accounts and sign-in | Must | Everyone | Customer: Sign-up, Sign-in. Staff: Sign-in |
| F01 | Master data | Must | Admin (read for all staff) | Staff: Master data |
| F02 | Create a booking | Must | Customer, Service advisor | Staff: New booking, Booking confirmed, Booking error states. Customer: Choose a service, Choose date and time, Booking confirmed |
| F03 | Free slots and resource allocation | Must | System (used by F02, F06, F09, F12) | No screen of its own |
| F04 | No double booking, no duplicate requests | Must | System | Staff: Booking error states |
| F05 | View appointments | Must | Customer (own only), all staff | Staff: Appointment list, Appointment details. Customer: My bookings, Details |
| F06 | Suggest other free times | Should | Customer, Service advisor | Staff: New booking. Customer: Choose date and time |
| F07 | Cancel an appointment | Should | Customer (own only), Service advisor | Staff: Appointment details. Customer: Details and cancel |
| F08 | Monitoring | Should | Admin | Staff: System monitoring |
| F09 | Reschedule | Could | Customer (own only), Service advisor | Staff: Reschedule. Customer: Change time |
| F10 | Temporary hold for self-booking | Could | Customer | Customer: Temporary hold |
| F11 | Notifications through the outbox | Could | System; Admin views the outbox | Staff: Notifications and outbox |
| F12 | Day-of-service operations | Could | Workshop foreman, Service advisor | Staff: Workshop board, Report absence and reassign, Appointment details |

**How to read the IDs.** FR = functional requirement, BR = business rule, AC = acceptance criterion in Given / When / Then form, written so each one becomes a test. Must = in the code with tests. Should = build if time allows. Could = describe in the SDD, schema ready.

**Out of scope for all features:** payments, parts stock, pricing, public holidays and lunch breaks.

## F00 · Accounts and sign-in

A customer creates an account with email and password, then signs in to the customer app. Staff sign in to the staff web with accounts from seed data.

**User story:** as a customer, I want to sign up with my email and a password, so I can book and manage my own appointments. As a staff member, I want to sign in with my work email, so I only see my dealership's data.

**Screens:** Customer app: Sign-up, Sign-in. Staff web: Sign-in. (These three screens are not in the design canvas yet.)

**API:** `POST /api/v1/auth/customer/sign-up`, `POST /api/v1/auth/customer/sign-in`, `POST /api/v1/auth/staff/sign-in`, `POST /api/v1/auth/sign-out`, `GET /api/v1/me`.

**Requirements**

- **FR-00.1** The customer Sign-up screen asks for: email, password, confirm password, full name, phone number.
- **FR-00.2** Email must be a valid address and unique. It is trimmed and stored in lower case, so `Hung@Mail.com` and `hung@mail.com` are the same account.
- **FR-00.3** Password: at least 8 characters, with at least one letter and one number (assumption). It is stored only as a hash (bcrypt or Argon2). It is never logged and never returned by any API.
- **FR-00.4** Sign-up creates the account and a linked customer record in one transaction. The customer is then signed in and lands on "Choose a service".
- **FR-00.5** Sign-in takes email and password. On success the user gets a session: a secure, HttpOnly cookie for the two web apps (Laravel Sanctum SPA mode), or a personal access token for other API clients. A session ends after 2 hours without activity (assumption).
- **FR-00.6** On a wrong email or password, the message is always "Email or password is incorrect". It never says which one was wrong.
- **FR-00.7** After 5 failed sign-ins for one email in 15 minutes, that email is blocked from signing in for 15 minutes (assumption).
- **FR-00.8** Staff accounts (service advisor, workshop foreman, admin) come from seed data, each with one role and one dealership. Seed passwords are set by environment variables, never written in code. There is no staff sign-up screen in this version.
- **FR-00.9** The customer sign-in only accepts customer accounts, and the staff sign-in only accepts staff accounts. Any other case gets the same "incorrect" message as FR-00.6.
- **FR-00.10** Sign-out ends the session: the old cookie or token can no longer be used.
- **FR-00.11** Every API except sign-up, sign-in and health checks needs a valid session. No session or an expired one: 401. Wrong role: 403.

**Business rules**

- **BR-00.1** One email = one account. A person who is both staff and customer needs two emails (assumption).
- **BR-00.2** A customer only sees and changes their own appointments and vehicles. The customer id always comes from the token, never from the request body.
- **BR-00.3** Staff only see data of their own dealership.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-00.1 | No account for `hung@mail.com` | Sign up with valid data | 201, the customer is signed in, a customer record exists |
| AC-00.2 | An account for `hung@mail.com` exists | Sign up again with `HUNG@mail.com` | 409 `EMAIL_ALREADY_USED` |
| AC-00.3 | Sign-up form | Password is `abc123` (6 characters) | 422 `WEAK_PASSWORD`, nothing saved |
| AC-00.4 | A customer account | Sign in with the wrong password | 401 `INVALID_CREDENTIALS`, message "Email or password is incorrect" |
| AC-00.5 | 5 failed sign-ins in the last 15 minutes | Sign in again, even with the right password | 429 `TOO_MANY_ATTEMPTS` |
| AC-00.6 | A seeded service advisor account | Sign in on the customer app | 401 `INVALID_CREDENTIALS` |
| AC-00.7 | A signed-in customer | Call `GET /dealerships/{id}/appointments` (staff list) | 403 `FORBIDDEN` |
| AC-00.8 | Customer A is signed in | Open customer B's appointment by id | 404 `APPOINTMENT_NOT_FOUND` (we do not reveal that it exists) |
| AC-00.9 | A signed-in user | Sign out, then call GET /me with the old session | 401 `UNAUTHENTICATED` |

**Out of scope in this version:** forgot password, email verification, sign-in with Google or Apple, two-step sign-in, a screen to create or manage staff accounts.

## F01 · Master data

The system knows what each dealership has, who can do which job, and when they work.

**Who:** Admin can view. All staff can read master data through the API. **Screen:** Staff web: Master data (read only in this version). **API:** `GET /api/v1/dealerships/{id}/service-types`, `/bays`, `/technicians`.

| Item | Required fields | Notes |
| --- | --- | --- |
| Dealership | id, name, time zone (for example `Asia/Ho_Chi_Minh`), opening hours per weekday | Opening hours are local time |
| Service type | id, name, duration in minutes, required skill, required bay type | Duration must be more than 0 |
| Service bay | id, dealership, name, bay type, active |  |
| Technician | id, dealership, name, skills, active |  |
| Technician shift | technician, weekday, start, end (local time) | A technician can have several shifts |
| Staff account | id, email, password hash, role, dealership | From seed data (F00) |
| Customer | id, account, full name, phone, email | Created at sign-up (F00) |
| Vehicle | id, customer, licence plate, VIN, make, model | VIN and plate are unique |

**Requirements**

- **FR-01.1** Seed data is loaded by a database migration on start: at least 1 dealership, 3 service types, 3 bays of 2 types, 4 technicians with different skills, 3 staff accounts (one per role), 2 customers and 3 vehicles.
- **FR-01.2** The Master data screen shows service types, bays, technicians and opening hours in tabs. It shows a note that data comes from migrations in this version.
- **FR-01.3** Read-only APIs return master data of the signed-in staff member's dealership only.

**Business rules**

- **BR-01.1** Each service type needs exactly one skill and one bay type. A technician qualifies when their skills include the required skill.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-01.1 | The app has just started on an empty database | Admin calls `GET /dealerships/{id}/service-types` | All 3 service types, each with duration and required skill |
| AC-01.2 | Admin of dealership D1 is signed in | Call the same API for dealership D2 | 404 `DEALERSHIP_NOT_FOUND` |

**Out of scope:** screens to add or change master data.

## F02 · Create a booking

A service advisor or a signed-in customer books one service for one vehicle at one time, and gets a confirmed appointment with a technician and a bay.

**User story:** as a service advisor (or a customer), I want to book a service for a vehicle at the time I choose, so I get a firm, confirmed appointment.

**Who:** Service advisor (any customer of their dealership), Customer (own vehicles only). **Screens:** Staff web: New booking, Booking confirmed, Booking error states. Customer app: Choose a service, Choose date and time, Booking confirmed. **API:** `POST /api/v1/appointments` with the `Idempotency-Key` header (UUID, required).

**Requirements**

- **FR-02.1** Check the input: all fields present, correct format, `desiredStart` has a time zone offset.
- **FR-02.2** Service advisor path: the advisor picks the customer and one of that customer's vehicles. Customer path: the customer id comes from the token (BR-00.2). The customer picks a saved vehicle or adds a new one (licence plate, make, model).
- **FR-02.3** End time = `desiredStart` + the service type's duration.
- **FR-02.4** Call F03 to find a technician and a bay, then save the booking as F04 says.
- **FR-02.5** Success: 201 Created, a `Location` header, an `ETag` header (version 1), and a body with id, status `CONFIRMED`, start, end, technician, bay, service type, vehicle, created time.
- **FR-02.6** The customer app path first creates a 5-minute hold (F10), then confirms it. The staff path confirms at once.

**Business rules**

- **BR-02.1** The start time must be in the future.
- **BR-02.2** The whole time range `[start, end)` must be inside the dealership's opening hours, in local time, on one day.
- **BR-02.3** One vehicle cannot have two active bookings that overlap (assumption: a car cannot be in two bays at once).

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-02.1 | A qualified technician and a matching bay are free 09:00–10:00 | Advisor books an oil change (60 min) at 09:00 | 201, `CONFIRMED`, technician and bay assigned, row saved |
| AC-02.2 | It is now 10:00 | Book 09:00 the same day | 422 `START_IN_PAST` |
| AC-02.3 | The dealership closes at 17:00 | Book a 60-minute service at 16:30 | 422 `OUTSIDE_OPENING_HOURS` |
| AC-02.4 | Vehicle v-002 belongs to another customer | Advisor books with customer c-001 and vehicle v-002 | 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER` |
| AC-02.5 | Customer A is signed in | Send a body with customer B's id | The body's customer id is ignored; the booking is for A only |
| AC-02.6 | Service type st-x does not exist | Book with st-x | 404 `SERVICE_TYPE_NOT_FOUND` |
| AC-02.7 | The vehicle is booked 09:00–10:00 | Book the same vehicle at 09:30 | 409 `VEHICLE_ALREADY_BOOKED` |
| AC-02.8 | No `Idempotency-Key` header | Send the request | 400 `IDEMPOTENCY_KEY_REQUIRED` |

## F03 · Free slots and resource allocation

For one time range and one service type, the system finds a technician and a bay that are both free for the whole range, or says clearly why there is none.

**Who:** System. It has no screen and no HTTP endpoint of its own; F02, F06, F09 and F12 call it. It must not depend on HTTP, so it can be tested without a web server.

**Requirements**

- **FR-03.1** Technicians: same dealership, active, have the required skill, and have a shift that covers the whole `[start, end)`.
- **FR-03.2** Bays: same dealership, active, the required bay type.
- **FR-03.3** Remove technicians and bays that already have an active booking overlapping the range.
- **FR-03.4** Rank technicians in a fixed order: fewest skills first, then fewest booked minutes that day, then by id. Rank bays by id. The same input always gives the same result, so it can be tested.
- **FR-03.5** The ranking rule sits behind one interface (`AllocationStrategy`), so it can be swapped later.
- **FR-03.6** When nothing is found, give a clear reason: `NO_QUALIFIED_TECHNICIAN` (nobody has the skill), `NO_TECHNICIAN_AVAILABLE` (busy or off shift), `NO_BAY_AVAILABLE`.

**Business rules**

- **BR-03.1** Two ranges `[s1, e1)` and `[s2, e2)` overlap only when s1 < e2 and s2 < e1.
- **BR-03.2** One service is done by one technician in one bay for the whole time. Work is not split between technicians (assumption).

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-03.1 | Technician An is booked 09:00–10:00 | Search 10:00–11:00 | An is a candidate (back to back is not an overlap) |
| AC-03.2 | An is booked 09:00–10:00 | Search 09:30–10:30 | An is removed |
| AC-03.3 | An is booked 08:00–12:00 | Search 09:00–10:00 (fully inside) | An is removed |
| AC-03.4 | A bay is free, every qualified technician is busy | Search that range | No result, reason `NO_TECHNICIAN_AVAILABLE` |
| AC-03.5 | A technician is free, every matching bay is busy | Search that range | Reason `NO_BAY_AVAILABLE` |
| AC-03.6 | Technician B's shift ends at 12:00 | Search 11:30–12:30 | B is removed |
| AC-03.7 | An has 1 skill, Chi has 4 skills, both qualify | Search | An is ranked first |
| AC-03.8 | No technician has the skill `EV_HIGH_VOLTAGE` | Search for the EV battery check | Reason `NO_QUALIFIED_TECHNICIAN` |

## F04 · No double booking, no duplicate requests

Two active bookings never share a technician, a bay or a vehicle at the same time, and a change based on old data is never saved. This holds with many requests at once, many app servers, and clients that send the same request again.

**Who:** System. **Screen:** Staff web: Booking error states (shows the messages users see).

**Requirements**

- **FR-04.1** Table `resource_allocation` (appointment, resource type, resource id, time range, active) has the constraint `EXCLUDE USING gist (resource_type WITH =, resource_id WITH =, period WITH &&) WHERE (active)`. Resource types: `TECHNICIAN`, `BAY`, `VEHICLE`.
- **FR-04.2** The appointment and its 3 allocation rows are saved in one transaction.
- **FR-04.3** If the constraint fails (PostgreSQL error `23P01`) on the technician or bay, try the next candidate pair, up to 3 times, then return 409 `NO_AVAILABILITY`. If it fails on the vehicle, return 409 `VEHICLE_ALREADY_BOOKED` at once.
- **FR-04.4** Each appointment has a `version`, starting at 1 and going up by 1 on every change. The API sends it as `ETag`.
- **FR-04.5** Every change to an existing appointment (F07, F09, F10 confirm, F12) needs `If-Match` with the version the user saw. Old version: 412 `APPOINTMENT_MODIFIED` with the latest data. No `If-Match`: 428 `PRECONDITION_REQUIRED`.
- **FR-04.6** Store the `Idempotency-Key` with a hash of the body and the response, for 24 hours (assumption). The key is checked before `If-Match`.
- **FR-04.7** Same key and same body: return the saved response (same status, same id) and save nothing new. Same key, different body: 422 `IDEMPOTENCY_KEY_MISMATCH`.
- **FR-04.8** Two requests with the same key at the same moment: only one is processed; the other gets 409 `REQUEST_IN_PROGRESS`.

**Business rules**

- **BR-04.1** Only allocations with `active = true` take part in the overlap check.
- **BR-04.2** The server never retries a 412 by itself. The user (or the background job) reads again and decides.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-04.1 | Only 1 matching technician and 1 bay | 20 parallel requests book 09:00 for 20 different cars | Exactly 1 × 201 and 19 × 409; one booking in the database |
| AC-04.2 | 2 matching technicians and 2 bays | 10 parallel requests for the same time | Exactly 2 × 201; no technician or bay is double-booked |
| AC-04.3 | A booking was made with key K | Send the same request with key K | 201 with the same id; booking count does not change |
| AC-04.4 | Key K was used for vehicle v-001 | Send key K with vehicle v-002 | 422 `IDEMPOTENCY_KEY_MISMATCH` |
| AC-04.5 | The first request with key K got 409 | Send key K again | The saved 409 is returned, nothing is run again |
| AC-04.6 | An appointment is at version 3 | Cancel and reschedule are sent together, both with `If-Match: "3"` | One gets 200, the other 412; final version is 4 |
| AC-04.7 | An appointment | Send a change with no `If-Match` | 428 `PRECONDITION_REQUIRED`, nothing saved |

Tests for F04 must run on a real PostgreSQL (for example Testcontainers). H2 and SQLite do not support `EXCLUDE`.

## F05 · View appointments

Staff see one appointment and the day's list. A customer sees only their own bookings.

**User story:** as an advisor or foreman, I want to see an appointment and the day's list, so I know which car goes to which bay with which technician. As a customer, I want to see my upcoming and past bookings.

**Who:** All staff (their dealership), Customer (own only). **Screens:** Staff web: Appointment list, Appointment details. Customer app: My bookings, Details. **API:** `GET /api/v1/appointments/{id}`, `GET /api/v1/dealerships/{id}/appointments?date=&technicianId=&bayId=&status=` (staff), `GET /api/v1/me/appointments?scope=upcoming|past` (customer).

**Requirements**

- **FR-05.1** `GET /appointments/{id}` returns the same fields as the F02 response, plus the `ETag` header.
- **FR-05.2** The staff list is sorted by start time, earliest first. `date` is read in the dealership's time zone. Filters: technician, bay, status.
- **FR-05.3** The customer list has two tabs, Upcoming and Past. Upcoming is sorted earliest first; Past is sorted latest first.
- **FR-05.4** Lists use cursor paging: default 50 items, at most 200.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-05.1 | A booking was made, then the app was restarted | `GET /appointments/{id}` | Same data as when it was created (it is stored for good) |
| AC-05.2 | Dealership at UTC+7 has a booking at 06:30 local time (23:30 UTC the day before) | Filter by that local date | The booking is in the result |
| AC-05.3 | No appointment with that id | `GET /appointments/{id}` | 404 `APPOINTMENT_NOT_FOUND` |
| AC-05.4 | Customer A has 2 bookings, customer B has 3 | A opens My bookings | A sees exactly 2 |

## F06 · Suggest other free times

Users see the free start times for a day, and a full slot comes back with the nearest other free times.

**User story:** as a customer, I want to see which times are still free for my service, so I do not have to guess one time after another.

**Who:** Customer, Service advisor. **Screens:** Staff web: New booking (time grid). Customer app: Choose date and time (time grid, "fully booked" card with the next free time). **API:** `GET /api/v1/dealerships/{id}/availability?serviceTypeId=&date=`.

**Requirements**

- **FR-06.1** Return the free start times in 15-minute steps (assumption), inside opening hours.
- **FR-06.2** A time is listed only when F03 finds at least one technician and bay for the whole service duration.
- **FR-06.3** A 409 from F02 includes up to 3 other free times nearest to the wanted time: first on the same day, then up to 7 days ahead.
- **FR-06.4** Load all allocations of the day in one query and compute in memory; do not query once per time slot.
- **FR-06.5** Times that are not free are shown crossed out, not hidden, so the grid keeps its shape.

**Business rules**

- **BR-06.1** The list is a hint, not a hold. Someone else may take a time before the user books it. Then F02 returns 409, and that is correct.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-06.1 | The only qualified technician is busy 09:00–10:00 | Get free times for a 60-minute service | 08:15 to 09:45 are not free; 08:00 and 10:00 are free |
| AC-06.2 | The dealership closes at 17:00 | Get free times for a 60-minute service | The latest time is 16:00 |
| AC-06.3 | 09:00 is full, 10:00 and 11:00 are free | Book 09:00 | 409 with alternatives starting at 10:00 |

## F07 · Cancel an appointment

A cancel frees the slot at once, so another customer can book it.

**User story:** as a service advisor, I want to cancel a booking when the customer says they cannot come, so the time can be sold again. As a customer, I want to cancel my own booking in the app.

**Who:** Service advisor, Customer (own only). **Screens:** Staff web: Appointment details (Cancel button). Customer app: Details and cancel (confirm sheet with a reason). **API:** `POST /api/v1/appointments/{id}/cancel` with body `{ "reason": "..." }`, headers `If-Match` and `Idempotency-Key`.

**Requirements**

- **FR-07.1** Set status to `CANCELLED` and set all its allocations to `active = false` in one transaction.
- **FR-07.2** Cancelling an appointment that is already cancelled returns 200 with its current state.
- **FR-07.3** The change uses the version check from F04 (`If-Match`), so it never overwrites a change made at the same time.
- **FR-07.4** The reason is required for staff and optional for customers. The customer app offers a short list of reasons plus "Other".

**Business rules**

- **BR-07.1** Only `CONFIRMED` and `HELD` bookings can be cancelled. A booking that has started or finished returns 422 `INVALID_STATUS_TRANSITION`.
- **BR-07.2** A customer can cancel only up to 2 hours before the start time (assumption). After that the app shows the dealership phone number.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-07.1 | Booking A at 09:00 uses the only technician | Cancel A, then book B at 09:00 | Cancel returns 200; B gets 201 |
| AC-07.2 | The booking is already `CANCELLED` | Cancel again | 200, status is still `CANCELLED` |
| AC-07.3 | The booking is `COMPLETED` | Cancel | 422 `INVALID_STATUS_TRANSITION` |
| AC-07.4 | A customer's booking starts in 1 hour | The customer cancels in the app | 422 `TOO_LATE_TO_CANCEL` |

## F08 · Monitoring

When something goes wrong, the team can answer "what happened to this request?" and can tell "the system is broken" apart from "the workshop is full".

**Who:** Admin. **Screen:** Staff web: System monitoring (health of app, database and outbox; booking counts; recent errors; search by correlation id). **API:** `GET /api/v1/monitoring/summary?range=1h|24h|7d`, `/health/live`, `/health/ready`, `/metrics`.

**Requirements**

- **FR-08.1** Logs are structured JSON. Each line has `correlationId`, `dealershipId`, `userId`, `appointmentId` (if any) and `outcome`. Logs never contain passwords, tokens, phone numbers or emails.
- **FR-08.2** Accept the `X-Correlation-Id` header from the client, or create one. Always return it in the response.
- **FR-08.3** Metrics: `booking_requests_total{outcome=confirmed|no_availability|validation_error|error}`, `booking_duration_seconds`, `allocation_retries_total`, `idempotent_replays_total`, `appointment_modified_total` (412), `sign_in_failures_total`.
- **FR-08.4** Tracing with OpenTelemetry, with spans for: receive request, check input, find candidates, write to the database.
- **FR-08.5** Health checks: `/health/live` and `/health/ready` (ready only when the database answers). `/metrics` is open only inside the network, not to the public.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-08.1 | The client sends no `X-Correlation-Id` | Call any API | The response has `X-Correlation-Id`; the log has the same value |
| AC-08.2 | No free slot | A booking returns 409 | `booking_requests_total{outcome="no_availability"}` goes up by 1 |
| AC-08.3 | The database is down | Call `/health/ready` | 503 |
| AC-08.4 | A service advisor is signed in | Open System monitoring | 403 `FORBIDDEN` (Admin only) |

## F09 · Reschedule

A booking moves to a new time only if the new time is free; otherwise the old booking stays exactly as it was.

**User story:** as a customer, I want to move my booking to another time without losing the old one if the new time is full.

**Who:** Service advisor, Customer (own only). **Screens:** Staff web: Reschedule. Customer app: Change time. **API:** `POST /api/v1/appointments/{id}/reschedule` with `newStart`, headers `If-Match` and `Idempotency-Key`.

**Requirements**

- **FR-09.1** In one transaction: check the version (F04), set the old allocations to `active = false`, run F03 for the new time, save the new allocations.
- **FR-09.2** If any step fails, roll back everything. The customer keeps the old booking, with the same technician and bay.
- **FR-09.3** Keep the same technician if they are free at the new time (customers like to see the same person).
- **FR-09.4** Success returns 200 and a new `ETag`. The screens show "Current time → New time" before the user confirms.

**Business rules**

- **BR-09.1** Only `CONFIRMED` bookings can be moved. The same 2-hour limit as cancel applies to customers (assumption).

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-09.1 | A booking at 09:00; 14:00 is full | Reschedule to 14:00 | 409; the 09:00 booking is still `CONFIRMED` with the same technician and bay |
| AC-09.2 | A booking at 10:00–11:00 in Bay 2; Bay 2 is free 11:00–11:30 | Move to 10:30–11:30 | 200; the booking does not clash with itself |
| AC-09.3 | The advisor cancelled the booking (version 4) | The customer reschedules with `If-Match: "3"` | 412 `APPOINTMENT_MODIFIED`; the app shows the latest data |

## F10 · Temporary hold for self-booking

The time a customer picks is kept for them for 5 minutes while they finish, so they do not lose it at the last step.

**User story:** as a customer booking online, I want my chosen time to be held while I enter my details, so I do not lose it at the last step.

**Who:** Customer (signed in). **Screen:** Customer app: Temporary hold (countdown, booking summary, contact details filled in from the account, vehicle picker). **API:** `POST /api/v1/appointments` with `mode: HOLD`; `POST /api/v1/appointments/{id}/confirm` with `If-Match` and `Idempotency-Key`.

**Requirements**

- **FR-10.1** `mode: HOLD` creates a `HELD` booking with `expiresAt` = now + 5 minutes (assumption). Its allocations are active, so the EXCLUDE check protects the held time like a real booking.
- **FR-10.2** Name and phone come from the customer's account. The customer picks a saved vehicle or adds a new one before confirming.
- **FR-10.3** Confirm changes `HELD` to `CONFIRMED` if the hold has not ended. If it has ended: 410 `HOLD_EXPIRED`.
- **FR-10.4** A job runs every 30 seconds (assumption). It changes expired holds to `EXPIRED` and frees their allocations, using the version check (`WHERE status = 'HELD' AND version = :v`).
- **FR-10.5** The booking flow also frees any expired hold that blocks the time it wants, so a late job never blocks a slot for long.
- **FR-10.6** One customer can have at most one active hold at a time (assumption). A new hold ends the old one.

**Business rules**

- **BR-10.1** Trade-off: customers get a better experience, but the workshop may have a time blocked for up to 5 minutes by someone who leaves.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-10.1 | A signed-in customer picks 10:00 | Tap "Hold and continue" | 201, status `HELD`, countdown starts at 05:00 |
| AC-10.2 | A hold created 6 minutes ago | Confirm | 410 `HOLD_EXPIRED` |
| AC-10.3 | The expiry job and the customer's confirm run at the same moment | Both finish | The booking is either `CONFIRMED` or `EXPIRED`, never both; the loser gets 412 or skips |
| AC-10.4 | Customer A holds 10:00 | Customer B asks for free times | 10:00 is not free for B |

## F11 · Notifications through the outbox

Confirmations, reminders and DMS updates are sent after the booking is saved, so a failing SMS or DMS never slows down or breaks a booking.

**Who:** System sends; Admin views. **Screen:** Staff web: Notifications and outbox (counts by state, filter by channel and event, retry a failed message). **API:** `GET /api/v1/outbox?status=&channel=`, `POST /api/v1/outbox/{id}/retry`.

**Requirements**

- **FR-11.1** Every status change writes one row to the `outbox` table in the same transaction: `AppointmentConfirmed`, `AppointmentCancelled`, `AppointmentRescheduled`, `AppointmentReminder`.
- **FR-11.2** A separate relay reads the outbox and sends to SMS, email or DMS. It retries up to 5 times with a longer wait each time, then marks the row `FAILED`.
- **FR-11.3** Delivery is "at least once". Receivers must ignore a repeat by event id.
- **FR-11.4** SMS and email go to the phone and email on the customer's account (F00).
- **FR-11.5** Admin can retry a `FAILED` message from the screen. The retry creates no new booking change.

**Business rules**

- **BR-11.1** Trade-off: a message may arrive a few seconds late, but a booking never fails because the SMS provider is down.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-11.1 | A new booking is saved | Look at the outbox | One `AppointmentConfirmed` row, created in the same transaction |
| AC-11.2 | The SMS provider is down | Book a service | 201 for the booking; the outbox row is retried later |
| AC-11.3 | A booking transaction is rolled back | Look at the outbox | No row for it, so no message is sent |
| AC-11.4 | A row failed 5 times | Admin taps Retry | Status goes back to `PENDING` and the relay sends it again |

## F12 · Day-of-service operations

The foreman records arrivals, no-shows and jobs that run late, and moves work when a technician is absent or a bay is broken.

**User story:** as a workshop foreman, I want to check cars in, mark customers who do not come, and move work when a technician is sick or a bay breaks.

**Who:** Workshop foreman (all actions), Service advisor (check-in, no-show). **Screens:** Staff web: Workshop board (timeline by bay, technician list), Report absence and reassign, Appointment details (Check in, Mark no-show). **API:** `POST /api/v1/appointments/{id}/check-in`, `/start`, `/extend`, `/complete`, `/no-show`; `POST /api/v1/technicians/{id}/absences`; `POST /api/v1/bays/{id}/out-of-service`. Path names are proposed. All changes need `If-Match` and `Idempotency-Key`.

**Requirements**

- **FR-12.1** Allowed status changes: `CONFIRMED → CHECKED_IN → IN_PROGRESS → COMPLETED`, and `CONFIRMED → NO_SHOW`. Any other change returns 422 `INVALID_STATUS_TRANSITION`.
- **FR-12.2** `COMPLETED` and `NO_SHOW` free the slot (`active = false`) in the same transaction.
- **FR-12.3** Mark a technician absent or a bay out of service for a time range. The system lists the affected bookings and tries to reassign each one with F03.
- **FR-12.4** Each reassignment is its own transaction with its own version check. A 412 on one booking does not stop the others.
- **FR-12.5** Bookings that cannot be reassigned go to a "contact the customer" list. They are never cancelled automatically.
- **FR-12.6** When a job runs late, the foreman can extend it (for example by 30 minutes). The extra time passes the EXCLUDE check. If it clashes with the next booking, that booking is handled as in FR-12.3.

**Business rules**

- **BR-12.1** No-show can be set only after the start time has passed.

**Acceptance criteria**

| AC | Given | When | Then |
| --- | --- | --- | --- |
| AC-12.1 | A `CONFIRMED` booking | Check in, start, complete | Status ends `COMPLETED`; allocations are inactive |
| AC-12.2 | A `COMPLETED` booking | Check in | 422 `INVALID_STATUS_TRANSITION` |
| AC-12.3 | Technician An has 3 bookings tomorrow; Chi is free for 2 of them | Mark An absent tomorrow | 2 bookings move to Chi; 1 goes to "contact the customer" |
| AC-12.4 | A job in Bay 1 runs late; the next booking in Bay 1 starts right after | Extend by 30 minutes | The extension is saved; the next booking is reassigned or listed for contact |

## Shared rules and open questions

Every feature follows these rules. Numbers marked "assumption" are ours; the brief does not give them.

**Errors.** All errors use RFC 9457 (`application/problem+json`) with `type`, `title`, `status`, `code`, `detail`, `correlationId`, and `alternatives` when a slot is full. Clients act on `code`, never on the message text.

| HTTP | Codes | When |
| --- | --- | --- |
| 400 | `VALIDATION_ERROR`, `IDEMPOTENCY_KEY_REQUIRED` | Wrong format, missing field or header |
| 401 | `UNAUTHENTICATED`, `INVALID_CREDENTIALS` | No session, session ended, wrong email or password |
| 403 | `FORBIDDEN` | The role may not use this feature |
| 404 | `DEALERSHIP_NOT_FOUND`, `SERVICE_TYPE_NOT_FOUND`, `VEHICLE_NOT_FOUND`, `APPOINTMENT_NOT_FOUND` | Not found, or it belongs to someone else |
| 409 | `NO_AVAILABILITY`, `VEHICLE_ALREADY_BOOKED`, `REQUEST_IN_PROGRESS`, `EMAIL_ALREADY_USED` | Clashes with the current data |
| 410 | `HOLD_EXPIRED` | The 5-minute hold has ended |
| 412 | `APPOINTMENT_MODIFIED` | The version in `If-Match` is old |
| 422 | `START_IN_PAST`, `OUTSIDE_OPENING_HOURS`, `VEHICLE_NOT_OWNED_BY_CUSTOMER`, `INVALID_STATUS_TRANSITION`, `IDEMPOTENCY_KEY_MISMATCH`, `WEAK_PASSWORD`, `TOO_LATE_TO_CANCEL` | Correct format, but breaks a business rule |
| 428 | `PRECONDITION_REQUIRED` | A change was sent without `If-Match` |
| 429 | `TOO_MANY_ATTEMPTS` | Too many failed sign-ins |

**Time.** The API uses ISO 8601 with an offset (`2026-11-02T09:00:00+07:00`). The database stores UTC. Time ranges are half-open `[start, end)`, so 09:00–10:00 and 10:00–11:00 do not overlap.

**Non-functional requirements**

| ID | Area | Requirement |
| --- | --- | --- |
| NFR-01 | Correctness | No double booking at any load, with many app servers (AC-04.1, AC-04.2 on real PostgreSQL) |
| NFR-02 | Performance | `POST /appointments` p95 under 300 ms; free times for one day p95 under 300 ms, for a dealership with 20 bays and 30 technicians |
| NFR-03 | Scalability | PHP-FPM workers are stateless; sessions and locks never live in one server's memory; every business table has `dealership_id` |
| NFR-04 | Reliability | Each business change is one transaction; every write API is safe to repeat; schema changes only through migrations |
| NFR-05 | Security | HTTPS only (Nginx); passwords hashed; sign-in rate limit; CSRF protection for cookie sessions; no personal data in logs; every query limited to the user's dealership or own data |
| NFR-06 | Testability | Unit tests for F03; feature tests for F00, F02, F04, F05 on real PostgreSQL; one command runs all tests |
| NFR-07 | Run | `docker compose up` starts Nginx, PHP-FPM, PostgreSQL and the React build, with seed data and seed accounts |