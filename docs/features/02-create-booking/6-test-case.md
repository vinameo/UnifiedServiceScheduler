# F02 · Create a booking · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Booking rules** (time, permissions, vehicle, service): HTTP tests on real PostgreSQL, one test per AC, frozen clock.
- **No double booking**, rerun through the real endpoint: 20 parallel processes (AC-04.1).
- **Screens**: component tests for the main flow and each error card; time formatting gets unit tests (dealership zone, never the browser's).
- **Performance**: one test measures p95 with NFR-02-sized data.
- **One manual run** in a browser through Nginx for both channels.

## A2. Manual scenarios

| # | Steps | Expected |
| --- | --- | --- |
| M1 | Advisor → New booking; type "hùng" → pick Nguyễn Văn Hùng | Vehicle shows only "Toyota Vios · 51H-123.45" |
| M2 | Pick Oil change, tomorrow, 09:00 → Confirm booking | "Appointment confirmed", `APT-…` reference, "Mon …, 09:00–10:00", Technician and Bay shown |
| M3 | "Book another", book the same vehicle at the same time | Card "Vehicle 51H-123.45 already has a booking at 09:00–10:00 that day" |
| M4 | Pick 20,000 km service, 16:00 | Card "The service would end after closing time … The latest start that day is 15:00." |
| M5 | Go offline (DevTools) and Confirm; go online; "Retry" | Panel "No response from the server yet"; after Retry there is only **one** booking |
| M6 | Click Confirm twice very fast | The button turns "Confirming…", only one request (Network tab) |
| M7 | Customer (phone, 390 px): Book → Oil change → tomorrow 10:00 → Continue → Add a new vehicle `51G-999.99` Honda City → Confirm appointment | "Booking confirmed", "We'll send a confirmation SMS to 0903 *** 218." |
| M8 | Customer presses F5 on step 3 | Service and time are still chosen |
| M9 | Sign in as foreman, open `/staff` | Redirect to the foreman home; New booking not shown |

## A3. Test data

Seed (F00, F01). Automated: `World::saigonD7()` + `customerWithVehicle()`; clock `Carbon::setTestNow('2026-11-02T08:00:00+07:00')` (Monday), except concurrency tests.

## A4. Not tested

Real SMS (F11); free-time grid (F06); holds (F10).

## A5. Exit criteria

Every TC green; M1–M9 pass and are recorded.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-02.1, AC-02.17, INV-02.8, FR-02.3, FR-02.5 | TC-02-01 |
| AC-02.2, BR-02.1 | TC-02-02, TC-02-16 |
| AC-02.3, BR-02.2 | TC-02-03, TC-02-16 |
| AC-02.4, D-02.5 | TC-02-04 |
| AC-02.5, INV-02.1 | TC-02-05 |
| AC-02.6, INV-02.3 | TC-02-06, TC-02-17 |
| AC-02.7, BR-02.3 | TC-02-07 |
| AC-02.8 | TC-02-08 |
| AC-02.9, INV-02.10 | TC-02-09, TC-02-24 |
| AC-02.10, D-02.5 | TC-02-10 |
| AC-02.11, FR-02.1, D-02.6 | TC-02-12 |
| AC-02.12 | TC-02-15 |
| AC-02.13 | TC-02-13 |
| AC-02.14, D-02.4 | TC-02-18 |
| AC-02.15, D-02.10 | TC-02-19, TC-02-23 |
| AC-02.16, INV-02.7 | TC-02-22 |
| AC-02.18 | TC-02-25 |
| AC-02.19 | TC-02-26 |
| AC-02.20 | TC-02-27 |
| AC-02.21 (AC-04.1 through the endpoint) | TC-02-20 |
| AC-04.3 through the endpoint | TC-02-21 |
| INV-02.2 | TC-02-04, TC-02-10, TC-02-11 |
| INV-02.4 | TC-02-03, TC-02-13, TC-02-14 |
| INV-02.5 | TC-02-02, TC-02-12, TC-02-15, TC-02-16 |
| INV-02.6, D-02.7 | TC-02-31 (error order) |
| INV-02.9, D-02.3 | TC-02-35 |
| D-02.2 | TC-02-31 (`mode: HOLD` → 400) |
| D-02.11 | TC-02-32 |
| D-02.12 | TC-02-34 |
| API §B4 | TC-02-31 |
| WEB-02.1–02.3 | TC-02-29 |
| WEB-02.4–02.9 | TC-02-25…TC-02-28, TC-02-32 |
| Error copy (README §5, WEB §B5) | TC-02-26, TC-02-28 |
| NFR-02, M-02.1 | TC-02-30 |

## B2. Test cases

Backend in `api/tests/Feature/Booking/CreateBooking/` unless noted.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-02-01 | AC-02.1, AC-02.17 | Integration | `World`; advisor; Hùng + Vios; An + K1 free | `POST /appointments` OIL 2026-11-02 09:00+07 | 201; `Location: /api/v1/appointments/{id}`; `ETag: "1"`; body `status CONFIRMED`, `start 09:00+07:00`, `end 10:00+07:00`, `technician`, `bay`, `serviceType`, `vehicle`, `createdAt`; 1 `appointments` row | An FR-02.5 field missing or wrong `end` | `CreateBookingTest::test_AC_02_1_advisor_books_and_gets_confirmed_appointment` |
| TC-02-02 | AC-02.2 | Integration | Clock 10:00 | Book 09:00 the same day | 422 `START_IN_PAST` | Compared with DB time | `…::test_AC_02_2_start_in_past` |
| TC-02-03 | AC-02.3 | Integration | Closes 17:00 | OIL at 16:30 | 422 `OUTSIDE_OPENING_HOURS`, `closesAt "17:00"`, `latestStart …T16:00:00+07:00` | Only `start` checked | `…::test_AC_02_3_end_after_closing` |
| TC-02-04 | AC-02.4 | Integration | Advisor; customer c1, vehicle v2 of c2 | Book c1 + v2 | 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER`; 0 bookings | Owner not checked | `…::test_AC_02_4_vehicle_of_another_customer` |
| TC-02-05 | AC-02.5, INV-02.1 | Integration | Customer A with a vehicle; customer B | A sends `customerId: B` + A's vehicle | 201; `customer.id = A` | `customerId` read from the body | `…::test_AC_02_5_customer_id_in_body_is_ignored` |
| TC-02-06 | AC-02.6 | Integration | — | `serviceTypeId` = a UUID that does not exist | 404 `SERVICE_TYPE_NOT_FOUND` | 500 or 400 | `…::test_AC_02_6_unknown_service_type` |
| TC-02-07 | AC-02.7 | Integration | Vehicle booked 09:00–10:00 (An, K1) | Book the same vehicle at 09:30 | 409 `VEHICLE_ALREADY_BOOKED`, correct `existingAppointment.id` | Returns `NO_AVAILABILITY` | `…::test_AC_02_7_vehicle_already_booked` |
| TC-02-08 | AC-02.8 | Integration | — | No `Idempotency-Key` header | 400 `IDEMPOTENCY_KEY_REQUIRED`; 0 bookings | Route missing `idempotent` | `…::test_AC_02_8_missing_idempotency_key` |
| TC-02-09 | AC-02.9, INV-02.10 | Integration | Customer | `POST /me/vehicles` `51G-999.99`; then `51g 99999` (different key) | 201 (`licencePlate "51G-999.99"`); 409 `PLATE_ALREADY_REGISTERED` | Plate not normalised | `Feature/Customers/MyVehiclesTest::test_AC_02_9_plate_is_unique_across_spellings` |
| TC-02-10 | AC-02.10, D-02.5 | Integration | B's vehicle | A books with it | 404 `VEHICLE_NOT_FOUND` (not 422) | Reveals the vehicle exists | `CreateBookingTest::test_AC_02_10_customer_cannot_use_foreign_vehicle` |
| TC-02-11 | INV-02.2 | Integration | — | Advisor and customer book with a `vehicleId` that does not exist | 404 `VEHICLE_NOT_FOUND` for both | — | `…::test_unknown_vehicle_is_404` |
| TC-02-12 | AC-02.11, D-02.6 | Integration | — | `desiredStart` `…T09:10:00+07:00`; `…T09:00:00`; `…T09:00:30+07:00` | 400 `VALIDATION_ERROR` on `desiredStart` for all three | Lenient `Carbon::parse` | `…::test_AC_02_11_offset_and_step_required` |
| TC-02-13 | AC-02.13 | Integration | Closed on Sunday | Book Sunday 09:00 | 422 `OUTSIDE_OPENING_HOURS`, `opensAt null` | A day without a row treated as open | `…::test_AC_02_13_closed_day` |
| TC-02-14 | INV-02.4, R-02.2 | Integration | Monday opens 08:00 | `desiredStart 2026-11-02T01:00:00Z` (= 08:00 Monday local) and `2026-11-01T23:30:00Z` (= 06:30 Monday local; the UTC date is Sunday) | 201; 422 `OUTSIDE_OPENING_HOURS` | Day taken from UTC | `…::test_INV_02_4_local_day_from_dealership_zone` |
| TC-02-15 | AC-02.12 | Integration | Clock 2026-11-02 | Book 2027-02-01 (91 days) and 2027-01-31 (90 days) | 422 `START_TOO_FAR_AHEAD` (`maxDate 2027-01-31`); 201 | Off by one day | `…::test_AC_02_12_ninety_day_horizon` |
| TC-02-16 | BR-02.1, BR-02.2 (boundaries) | Integration | Clock 09:00:00 | Book exactly 09:00; book OIL at 16:00 | 422 `START_IN_PAST`; 201 (`end` = 17:00, exactly closing) | Wrong `>=`/`>` | `…::test_boundaries_now_and_closing_time` |
| TC-02-17 | INV-02.3 | Integration | An inactive service; a service of dealership D2 | Book with each | 404 `SERVICE_TYPE_NOT_FOUND` for both | `active`/dealership not filtered | `…::test_inactive_or_foreign_service_type` |
| TC-02-18 | AC-02.14 | Integration | Foreman, admin | `POST /appointments` | 403 `FORBIDDEN` | Wrong Gate | `…::test_AC_02_14_foreman_and_admin_cannot_book` |
| TC-02-19 | AC-02.15 | Integration | Seed customers | Advisor `?query=hu`, `?query=hùng`, `?query=0903`, `?query=hung@`; customer `?query=hùng` | 400; Hùng; Hùng; Hùng; 403 | Gate missing or wrong column | `Feature/Customers/CustomerSearchTest::test_AC_02_15_search_rules` |
| TC-02-20 | AC-02.21, AC-04.1 | Concurrency | Only An + K1; 20 customers, 20 vehicles | 20 processes `POST /api/v1/appointments` at the same moment (different keys) | 1 × 201, 19 × 409; 1 booking; overlap 0 | The endpoint bypasses `AppointmentWriter` | `ParallelCreateBookingTest::test_AC_04_1_through_the_real_endpoint` |
| TC-02-21 | AC-04.3 | Integration | Booked with K | Send the identical request with K | 201 with the same `id`; `Idempotent-Replayed: true`; 1 booking, 1 outbox event | Endpoint missing `idempotent` | `CreateBookingTest::test_AC_04_3_replay_through_the_real_endpoint` |
| TC-02-22 | AC-02.16, INV-02.7, AC-11.1 | Integration | — | A successful booking | `outbox_messages`: 1 `AppointmentConfirmed` `event_id`, 3 channels, right `appointment_id`; 3 active allocations | SMS called in the request | `…::test_AC_02_16_confirmed_booking_writes_outbox` |
| TC-02-23 | D-02.10, API §B4 | Integration | Customer named "100% Auto" | `?query=%%%`; `?query=100%` | `%%%`: empty; `100%`: "100% Auto" | `LIKE` not escaped | `CustomerSearchTest::test_like_wildcards_are_escaped` |
| TC-02-24 | D-02.9 | Integration | Customer with 10 vehicles; another vehicle with VIN V | Add vehicle 11; add a vehicle with VIN V in lower case | 422 `VEHICLE_LIMIT_REACHED`; 409 `VIN_ALREADY_REGISTERED` | No limit | `MyVehiclesTest::test_vehicle_limit_and_vin_unique` |
| TC-02-25 | AC-02.18 | Web component | Fake API: customer search, vehicles, services, 201 | Pick customer → vehicle → Oil change → 09:00 → Confirm | The request has `Idempotency-Key`, `desiredStart …T09:00:00+07:00`; goes to the confirmed page: "Appointment confirmed", "APT-20261102-0042", "Monday, 2 Nov 2026, 09:00–10:00" | Formatted in the browser's zone | `web/src/staff/booking/NewBookingPage.test.tsx` (run with `TZ=America/New_York`) |
| TC-02-26 | AC-02.19 | Web component | Fake API 422 `OUTSIDE_OPENING_HOURS` (`latestStart 15:00`) | Confirm 20,000 km at 16:00 | Card "The service would end after closing time" + "The latest start that day is 15:00." | Server `detail` shown | `…/NewBookingPage.test.tsx` |
| TC-02-27 | AC-02.20 | Web component | Fake API: services, `me`, no vehicles, `POST /me/vehicles` 201, `POST /appointments` 201 | Go through 3 steps, add a vehicle, confirm | `POST /me/vehicles` first, then `POST /appointments` with the new `vehicleId`; "Booking confirmed" page with "0903 *** 218" | Booking sent before the vehicle | `web/src/customer/booking/BookingFlow.test.tsx` |
| TC-02-28 | WEB-02.6 | Web component | Fake API: 409 `NO_AVAILABILITY` (`NO_BAY_AVAILABLE`); 422 `VEHICLE_NOT_OWNED_BY_CUSTOMER` | Confirm | Card heading "The 09:00 slot was just taken" + "No Quick service bay is free from 09:00 to 10:00…"; the second error shows under the Vehicle field | `reason` ignored | `web/src/shared/booking/BookingErrorCard.test.tsx` |
| TC-02-29 | WEB-02.1–02.3 | Web unit | — | `formatInZone('2026-11-01T23:30:00Z','Asia/Ho_Chi_Minh','dayLong')`; `localSlotsForDay` Saturday (08–12), 60 min; `maskPhone('0903123218')` | "Monday, 2 Nov 2026"; 08:00…11:00 (13 times); "0903 *** 218" | Machine time zone used | `web/src/shared/format/*.test.ts` |
| TC-02-30 | NFR-02, M-02.1 | Integration (`@group performance`) | Dealership with 20 bays, 30 technicians, 200 bookings that day | 50 sequential `POST /appointments` (different times) | p95 < 300 ms | N+1 snapshot or missing index | `CreateBookingPerformanceTest` |
| TC-02-31 | API §B4, INV-02.6, D-02.7 | Integration | — | Each row of the adversarial input table; plus a body with both a wrong service and a past time | Status + `code` as in the table; the double case → 404 `SERVICE_TYPE_NOT_FOUND` (first error per D-02.7) | Different check order | `CreateBookingAdversarialInputTest` (data provider) |
| TC-02-32 | D-02.11 | Web component | Draft at step 3 | Unmount + mount again (simulated F5); fake API returns 409 `NO_AVAILABILITY` on confirm | The draft survives; on 409 back to `/book/time` with an Alert, the old time unselected | Draft only in memory | `BookingFlow.test.tsx` |
| TC-02-33 | All | Manual | Stack running | M1–M9 | As A2 | — | Record in A2 |
| TC-02-34 | D-02.12, D-02.3 | Integration + Web | Two dealerships | Customer books without `dealershipId`; with a missing `dealershipId`; web step 1 | 400; 404 `DEALERSHIP_NOT_FOUND`; the web shows the "Dealership" field | — | `CreateBookingTest::test_customer_must_name_a_dealership`, `ChooseServicePage.test.tsx` |
| TC-02-35 | INV-02.9, D-02.3 | Integration | Advisor of D1 | Sends `dealershipId: D2` | 201; the booking belongs to D1 | Body `dealershipId` used | `CreateBookingTest::test_advisor_dealership_comes_from_account` |

## B3. Fixtures

- `World::saigonD7()`, `customerWithVehicle()`, `onlyTechnicians()` (F01).
- `Tests\Support\Booking\BookingPayload::advisor(World, …)` / `::customer(…)`: build a valid body; tests change one field.
- `Tests\Support\FreezesClock` (README §7).
- Web: `apiMock` (F00), `renderWithProviders` (existing), JSON fixture `web/src/test/fixtures/appointment.ts` (reused from F05 on).

## B4. Regression

All F00, F01, F03, F04 tests (especially `ParallelBookingTest`, `IdempotencyTest`); `web/src/router.test.tsx`.
