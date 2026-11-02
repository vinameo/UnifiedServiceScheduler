# F10 · Temporary hold for self-booking · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Holds behave like bookings** for everyone else: HTTP tests that hold a time and check other customers' availability and bookings.
- **Expiry**: frozen-clock HTTP tests for confirm after expiry; a command test for the job; a test for the releaser when the job is not running.
- **The race** between the job and confirm: real parallel processes, repeated 100 times (SDD §13).
- **Screens**: component tests for the countdown and the expired state; one manual run.

## A2. Manual scenarios

| # | Steps | Expected |
| --- | --- | --- |
| M1 | Customer → Oil change → tomorrow 10:00 → Hold and continue | "Slot held for you" with a countdown from about 05:00 |
| M2 | In another browser, customer B opens the same day | 10:00 struck through |
| M3 | Customer A picks the saved vehicle → Confirm appointment | "Booking confirmed" |
| M4 | Hold another time and wait 5 minutes | Timer red under 1 minute; at 00:00 "Your hold has expired"; "Pick a time again" keeps service and vehicle |
| M5 | Stop the scheduler (`docker compose stop scheduler`), hold, wait 6 minutes, book that time as another customer | Booking succeeds |
| M6 | Advisor tries to hold through the API (`mode: HOLD`) | 422 `HOLD_NOT_ALLOWED` |

## A3. Test data

`World`, `onlyTechnicians(['An'])` for single-pair cases; clock frozen, except in the concurrency test. The race test (TC-10-03) creates holds with the factory, sets `expires_at` to a real moment T about 2 seconds ahead, starts the confirm at T − 300 ms (so its read sees a valid hold) and runs `holds:expire --now=T+1s` at the same moment (so the job sees it expired); both reach the versioned update together.

## A4. Not tested

Real SMS on confirmation (F11).

## A5. Exit criteria

Every TC green; TC-10-03 green 3 runs in a row; M1–M6 pass.

---

# PART B · For implementing agents

## B0. Reading rules

> - **MUST** / **MUST NOT**: required. **SHOULD**: do it unless the commit gives a reason. **MAY**: optional. `CLAUDE.md` applies in full.
> - The requirements are the ACs, INVs, permissions and copy. Code sketches, queries and step orders are **hypotheses**: the agent checks them against the code and real data at HEAD (`implement-ticket` step 4).
> - A wrong hypothesis whose sound version keeps every AC, permission and copy → build the sound version, note it in the commit and fix the spec. A sound version that changes what users see → stop and ask.

## B1. Coverage matrix

| Requirement | TC |
| --- | --- |
| AC-10.1, FR-10.1, D-10.3 | TC-10-01, TC-10-14 |
| AC-10.2, FR-10.3, D-10.6 | TC-10-02 |
| AC-10.3, FR-10.4, INV-10.3, D-10.7 | TC-10-03 |
| AC-10.4, INV-10.1 | TC-10-04 |
| AC-10.5, FR-10.6, INV-10.2 | TC-10-05 |
| AC-10.6, D-10.4 | TC-10-06 |
| AC-10.7, INV-10.4, D-10.10 | TC-10-08 |
| AC-10.8, D-10.2 | TC-10-09 |
| AC-10.9, FR-10.5, INV-10.6, D-10.9 | TC-10-12 |
| AC-10.10, D-10.8, INV-10.5 | TC-10-13 |
| AC-10.11, D-10.1 | TC-10-15 |
| AC-10.12 | TC-10-16 |
| INV-10.2 (parallel) | TC-10-07, TC-10-21 |
| INV-10.7 | TC-10-10 |
| INV-10.8 | TC-10-02, TC-10-11 |
| D-10.11 | TC-10-18 |
| DB-10.1 migration pre-check | TC-10-17 |
| API §B4 | TC-10-19 |
| Confirm at the boundary / replay | TC-10-11 |

## B2. Test cases

Backend in `api/tests/Feature/Booking/Hold/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-10-01 | AC-10.1 | Integration | Customer; clock 08:00 | `POST /appointments` `mode HOLD` 10:00, no vehicle | 201, `status HELD`, `expiresAt` = 08:05+07:00, `vehicle null`; 2 active allocations (TECHNICIAN, BAY) | No allocations for the hold | `HoldTest::test_AC_10_1_hold_blocks_the_slot` |
| TC-10-02 | AC-10.2 | Integration | Hold created at 08:00 | `travel(6)->minutes()`; confirm with `If-Match "1"` | 410 `HOLD_EXPIRED`; status `EXPIRED`; allocations inactive | Confirms an expired hold | `ConfirmHoldTest::test_AC_10_2_expired_hold_cannot_be_confirmed` |
| TC-10-03 | AC-10.3, INV-10.3 | Concurrency (`@group slow`) | 100 holds (half with a vehicle, half without), each with `expires_at` = T | For each, process 1 runs `holds:expire --now=T+1s`, process 2 confirms at T − 300 ms, released together | Each booking ends `CONFIRMED` (3 active allocations) or `EXPIRED` (0 active); never mixed; the losing confirm got 412 (status `EXPIRED`); across the 100 runs **both** outcomes occur at least once (otherwise the test did not race) | Both writes apply, or the race was never exercised | `ParallelHoldExpiryTest::test_AC_10_3_job_and_confirm_never_both_win` |
| TC-10-04 | AC-10.4 | Integration | Customer A holds 10:00 (An only) | Customer B `GET availability` | 10:00 `available: false`; B's `POST` 10:00 → 409 | Holds ignored by availability | `HoldTest::test_AC_10_4_held_slot_is_not_free_for_others` |
| TC-10-05 | AC-10.5 | Integration | A holds 10:00 | A holds 11:00 | 10:00 hold `EXPIRED` with inactive allocations; 11:00 `HELD`; exactly one `HELD` for A | Two holds | `…::test_AC_10_5_new_hold_ends_the_old_one` |
| TC-10-06 | AC-10.6 | Integration | A holds 10:00; 11:00 full | A holds 11:00 | 409 `NO_AVAILABILITY`; the 10:00 hold still `HELD`, version unchanged | Old hold expired outside the transaction | `…::test_AC_10_6_failed_new_hold_keeps_the_old_one` |
| TC-10-07 | INV-10.2, D-10.4 | Concurrency | A has no hold | 2 processes hold 10:00 and 11:00 for A at once | Exactly one `HELD` remains; both responses 201 | Customer row not locked (23505 or 500) | `ParallelHoldTest::test_INV_10_2_one_hold_per_customer_in_parallel` |
| TC-10-08 | AC-10.7 | Integration | A's hold, vehicle V of A | Confirm `{vehicleId: V}` with `If-Match "1"` | 200 `CONFIRMED`, `expiresAt null`, `ETag "2"`; 3 active allocations; 1 `AppointmentConfirmed` event; history `CONFIRMED` | Vehicle allocation missing | `ConfirmHoldTest::test_AC_10_7_confirm_adds_the_vehicle` |
| TC-10-09 | AC-10.8 | Integration | V has another booking at the held time | Confirm with V | 409 `VEHICLE_ALREADY_BOOKED`; hold still `HELD`, version 1 | Status changed before the vehicle insert failed | `…::test_AC_10_8_vehicle_clash_keeps_the_hold` |
| TC-10-10 | INV-10.7 | Integration | A's hold | B confirms it; advisor confirms it; A confirms with B's vehicle | 404 `APPOINTMENT_NOT_FOUND`; 403; 404 `VEHICLE_NOT_FOUND` | Permission leak | `…::test_INV_10_7_only_the_owner_confirms` |
| TC-10-11 | INV-10.4, INV-10.8 | Integration | Hold expiring at 08:05:00 | Confirm at 08:04:59; another hold confirmed at exactly 08:05:00; resend the first confirm with the same key | 200; 410; the saved 200 | Wrong boundary or replay 422 | `…::test_boundary_and_replay` |
| TC-10-12 | AC-10.9 | Integration | Expired hold (job not run) holding An, the only technician, 10:00 | Customer B books 10:00 | 201; the old hold `EXPIRED` | Releaser not called | `ExpiredHoldReleaserTest::test_AC_10_9_booking_flow_frees_expired_holds` |
| TC-10-13 | AC-10.10 | Integration | 3 expired holds, 1 valid | `artisan holds:expire` | 3 `EXPIRED` with inactive allocations and history rows (actor null); the valid one unchanged; no outbox rows | Expires valid holds | `ExpireHoldsCommandTest::test_AC_10_10_job_expires_only_expired_holds` |
| TC-10-14 | AC-10.1 (web) | Web component | Fake API: hold 201 with `expiresAt` 5 minutes after the `Date` header; browser clock 2 minutes off | Tap "Hold and continue" | Navigates to the hold page; countdown shows 05:00 (server time), not 03:00 | Browser clock used | `web/src/customer/booking/HoldPage.test.tsx` |
| TC-10-15 | AC-10.11 | Integration | Advisor | `POST /appointments` with `mode: HOLD` | 422 `HOLD_NOT_ALLOWED` | Staff can hold | `HoldTest::test_AC_10_11_staff_cannot_hold` |
| TC-10-16 | AC-10.12 | Web component | Fake timers; fake API confirm → 410 | Let the countdown reach 0; separately confirm and get 410; also 412 with `status EXPIRED` | Expired state with the copy; "Pick a time again" → `/book/time` with the vehicle kept in the draft | Generic error shown | `…/HoldPage.test.tsx` |
| TC-10-17 | DB-10.1 | Integration | Two `HELD` rows for one customer inserted before the migration | Run the migration | It stops with the message; after expiring one, it runs | Index creation fails with a raw error | `HoldIndexMigrationTest` |
| TC-10-18 | D-10.11 | Web unit | Response `Date` 10:00:00, browser 09:58:00 | `useServerClock().now()` | 10:00:00 (± test tolerance) | Offset ignored | `web/src/shared/time/useServerClock.test.ts` |
| TC-10-19 | API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `HoldAdversarialInputTest` |
| TC-10-20 | All | Manual | Stack running | M1–M6 | As A2 | — | Record in A2 |
| TC-10-21 | INV-10.2, D-10.4 | Concurrency | A holds 09:00 (v1) | 2 processes hold 10:00 and 11:00 for A at once | Both 201 (never 412); exactly one `HELD`; the 09:00 hold and the earlier new hold are `EXPIRED` with inactive allocations | Both transactions expire the same old hold → the loser gets 412 | `ParallelHoldTest::test_D_10_4_parallel_holds_with_an_existing_hold` |

## B3. Fixtures

`AppointmentFactory::hold($customer, $at, expiresAt: …)`; `Parallel` (F04); `FreezesClock`; web fake timers (Vitest).

## B4. Regression

F02 (customer flow tests change from `/book/details` to `/book/hold/:id` — update the flow test, keep its meaning), F04 (`BusyIntervalReaderTest`, `TransitionTest`), F06 (`AvailabilityTest`), F09 (`RescheduleTest`).
