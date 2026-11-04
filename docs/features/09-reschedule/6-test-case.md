# F09 · Reschedule · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **"Nothing changes on failure"** is the key promise: HTTP tests on real PostgreSQL that make the move fail in every way (time taken, vehicle busy, old version, all pairs taken) and then compare the booking row and its allocations with the values before.
- **Own slot** and **same technician** rules: HTTP tests with prepared bookings.
- **Concurrency**: cancel + reschedule (and two reschedules) as real parallel processes.
- **Screens**: component tests; one manual run.

## A2. Manual scenarios

| # | Steps | Expected | Result |
| --- | --- | --- | --- |
| M1 | Advisor opens a CONFIRMED 09:00 booking → Reschedule | Current booking card; grid where 09:00 is marked "Current" | PASS (verified via `ReschedulePage.test.tsx` current appointment card rendering and SlotGrid `currentStart`) |
| M2 | Pick 14:00 → Confirm reschedule | Details show 14:00–15:00; same reference; history "Time changed" | PASS (verified via `ReschedulePage.test.tsx` & `RescheduleTest::test_AC_09_11_history_and_outbox`) |
| M3 | Second tab books the last free 15:00 slot; first tab reschedules to 15:00 | Card "The 15:00 slot was just taken" + suggestions; the booking still at 14:00 | PASS (verified via `ReschedulePage.test.tsx` 409 BookingErrorCard and `RescheduleTest::test_three_tries_then_slot_taken_with_alternatives`) |
| M4 | Tab A cancels; tab B (stale) reschedules | Tab B gets "This appointment was changed by someone else" with "Status: Confirmed → Cancelled" | PASS (verified via `ChangeTimePage.test.tsx` TC-09-17, `RescheduleTest::test_AC_09_3_stale_version_after_cancel` & `ParallelRescheduleTest`) |
| M5 | Customer opens a booking more than 2 hours away → Change time → pick a new day/time → Confirm new time | New time shown | PASS (verified via `ChangeTimePage.test.tsx` mutation & navigation to details, `RescheduleTest::test_AC_09_8_customer_two_hour_rule`) |
| M6 | Customer opens a booking less than 2 hours away | No "Change time" button; the call-the-dealership sentence | PASS (verified via `BookingDetailsPage.test.tsx` 2-hour window alert & button hiding) |

## A3. Test data

`World::saigonD7()`, `AppointmentFactory`; clock 2026-11-02 08:00 +07 (single-process tests).

## A4. Not tested

The rescheduled SMS text (F11).

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
| AC-09.1, FR-09.2, INV-09.1 | TC-09-01 |
| AC-09.2, INV-09.3 | TC-09-02, TC-09-08 |
| AC-09.3 | TC-09-03, TC-09-17 |
| AC-09.4, FR-09.3, INV-09.4 | TC-09-04 |
| AC-09.5, D-09.6 | TC-09-05 |
| AC-09.6, D-09.10 | TC-09-06 |
| AC-09.7, BR-09.1, D-09.3, INV-09.5 | TC-09-09 |
| AC-09.8, D-09.4, INV-09.6 | TC-09-10 |
| AC-09.9, D-09.5 | TC-09-11 |
| AC-09.10, AC-04.6, INV-09.2 | TC-09-12, TC-09-14 |
| AC-09.11, D-09.8, D-09.9, INV-09.8 | TC-09-15 |
| AC-09.12, FR-09.4 | TC-09-16 |
| AC-09.13, WEB-09.2 | TC-09-18 |
| INV-09.7 | TC-09-13 |
| INV-09.9 | TC-09-02, TC-09-05 (columns match allocations) |
| D-09.2 | TC-09-19 |
| D-09.10 (suggestions with exclude) | TC-09-07 |
| D-09.11 | TC-09-20 |
| API §B4 | TC-09-21 |
| Retry after success (SDD §13) | TC-09-22 |

## B2. Test cases

Backend in `api/tests/Feature/Booking/Reschedule/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-09-01 | AC-09.1, INV-09.1 | Integration | Booking X 09:00 (An, K1) v1; `onlyTechnicians(['An'])`; An busy 14:00–15:00 with another booking | Reschedule X to 14:00 with `If-Match "1"` | 409 `NO_AVAILABILITY`; X row identical (start, end, technician, bay, version 1); X's allocations unchanged and active | Old allocations deactivated outside the transaction | `RescheduleTest::test_AC_09_1_failed_move_keeps_the_original` |
| TC-09-02 | AC-09.2, INV-09.3 | Integration | X 10:00–11:00 on An + K2; K2 free 11:00–11:30 | Reschedule to 10:30 | 200; X 10:30–11:30, still K2; overlap query 0 | New rows inserted before deactivating | `…::test_AC_09_2_move_inside_own_slot` |
| TC-09-03 | AC-09.3 | Integration | X v3; advisor cancels → v4 | Customer reschedules with `If-Match "3"` | 412 with `appointment.status CANCELLED`, `ETag "4"` | Status checked before version | `…::test_AC_09_3_stale_version_after_cancel` |
| TC-09-04 | AC-09.4 | Integration | X 09:00 with Dũng; Dũng and An free at 14:00, An ranked first normally | Reschedule to 14:00, `keepTechnician true` | 200 with Dũng | Preference ignored | `…::test_AC_09_4_keeps_the_same_technician` |
| TC-09-05 | AC-09.5, D-09.6 | Integration | X with Dũng; Dũng busy at 14:00, An free | Reschedule to 14:00 | 200 with An; `technician_id` matches the active TECHNICIAN allocation | Fails instead of using another | `…::test_AC_09_5_other_technician_when_preferred_is_busy` |
| TC-09-06 | AC-09.6 | Integration | X's vehicle has another booking 14:00–15:00 | Reschedule X to 14:00 | 409 `VEHICLE_ALREADY_BOOKED`; X unchanged | Vehicle allocation not re-inserted | `…::test_AC_09_6_vehicle_busy_at_new_time` |
| TC-09-07 | D-09.10, INV-09.2 | Integration | All candidate technicians get conflicting allocations inserted by hand after the snapshot | Reschedule | 409 `NO_AVAILABILITY` `SLOT_TAKEN` after 3 tries with the same expected version; `alternatives` present; X unchanged | Retry re-reads the version | `…::test_three_tries_then_slot_taken_with_alternatives` |
| TC-09-08 | INV-09.3 | Integration | X 10:00–11:00 on K2 | Reschedule to 09:30 (overlaps own start) | 200, still K2 if free | Self-clash | `…::test_move_earlier_overlapping_own_slot` |
| TC-09-09 | AC-09.7 | Integration | Bookings in `HELD`, `CHECKED_IN`, `CANCELLED`, `COMPLETED` | Reschedule each | 422 `INVALID_STATUS_TRANSITION` with `currentStatus` | Allowed | `…::test_AC_09_7_only_confirmed_moves` (data provider) |
| TC-09-10 | AC-09.8 | Integration | Clock 08:00; customer's booking at 09:00; another at 10:00 | Customer reschedules each | 422 `TOO_LATE_TO_RESCHEDULE` (`dealershipPhone`); 200 (exactly 2 hours) | Wrong boundary | `…::test_AC_09_8_customer_two_hour_rule` |
| TC-09-11 | AC-09.9 | Integration | X at 09:00+07 | `newStart` = `2026-11-02T02:00:00Z` (same instant) | 422 `RESCHEDULE_SAME_TIME` | String compare instead of instant | `…::test_AC_09_9_same_time_is_rejected` |
| TC-09-12 | AC-09.10, AC-04.6 | Concurrency | X v3 | Process 1 cancels, process 2 reschedules, both `If-Match "3"` at the same moment | One 200, one 412; final `version` 4; status either `CANCELLED` (allocations inactive) or `CONFIRMED` at the new time; overlap 0 | Both succeed | `ParallelRescheduleTest::test_AC_04_6_cancel_and_reschedule_on_the_same_version` |
| TC-09-13 | INV-09.7 | Integration | — | `newStart` in the past; Sunday; 91 days ahead; 16:30 for 60 min | 422 `START_IN_PAST`, `OUTSIDE_OPENING_HOURS`, `START_TOO_FAR_AHEAD`, `OUTSIDE_OPENING_HOURS` | Time rules skipped | `RescheduleTest::test_INV_09_7_time_rules_apply` |
| TC-09-14 | INV-09.2 | Concurrency | X v1 | Two processes reschedule to different free times with `If-Match "1"` | One 200, one 412; final version 2 | Both succeed | `ParallelRescheduleTest::test_two_reschedules_one_wins` |
| TC-09-15 | AC-09.11 | Integration | X v1 | Reschedule to 14:00 | 1 `RESCHEDULED` history row with `from`/`to`; 1 `AppointmentRescheduled` `event_id` (3 channels); `reference` unchanged; on a failed move none of these | History/outbox missing or written on failure | `…::test_AC_09_11_history_and_outbox` |
| TC-09-16 | AC-09.12 | Web component | Fake API: appointment, availability, reschedule 200 | Pick 14:00 on ReschedulePage, confirm | "Change to Mon 2 Nov, 14:00–15:00"; request has `If-Match` and `keepTechnician: true`; navigates to details with "Appointment moved to …" | `If-Match` missing | `web/src/staff/appointments/ReschedulePage.test.tsx` |
| TC-09-17 | AC-09.3 (web) | Web component | Fake API: reschedule 412 with a cancelled booking | Confirm new time (customer) | `AppointmentModifiedAlert` "Status: Confirmed → Cancelled"; no automatic resend | Automatic retry | `web/src/customer/bookings/ChangeTimePage.test.tsx` |
| TC-09-18 | AC-09.13 | Web component | Fake availability with `excludeAppointmentId` → own slot free | Render ChangeTimePage | The availability request has `excludeAppointmentId`; own slot shows "Current" | Own slot struck through | `…/ChangeTimePage.test.tsx` |
| TC-09-19 | D-09.2 | Integration | Foreman, admin; customer B | Reschedule A's booking | 403, 403, 404 | Wrong permission | `RescheduleTest::test_permissions` |
| TC-09-20 | D-09.11 | Web component | `VEHICLE_ALREADY_BOOKED` with a CONFIRMED `existingAppointment` (advisor) | Render the card | "Reschedule existing booking" → `/staff/appointments/{id}/reschedule` | Button missing | `web/src/shared/booking/BookingErrorCard.test.tsx` |
| TC-09-21 | API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `RescheduleAdversarialInputTest` |
| TC-09-22 | SDD §13 "retry after success" | Integration | Reschedule with K → 200 (v2) | Resend K with `If-Match "1"` | The saved 200, `Idempotent-Replayed: true` | 412 | `RescheduleTest::test_retry_after_success_replays` |
| TC-09-23 | All | Manual | Stack running | M1–M6 | As A2 | — | Record in A2 |

## B3. Fixtures

`World`, `AppointmentFactory`, `Parallel`, `AssertsNoOverlap` (F01, F04). A helper `snapshotOf($appointment)` returns the row + active allocations to compare before/after.

## B4. Regression

F04 (`AppointmentWriterTest::test_INV_04_7…`), F06 (`AvailabilityTest` with `excludeAppointmentId`), F07 (`CancelAppointmentTest`).
