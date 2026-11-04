# F07 · Cancel an appointment · Test cases (TC)

---

# PART A · For QA and the product owner

## A1. Strategy

- **Cancel frees the slot at once**: HTTP test that cancels and then books the same time, on real PostgreSQL.
- **Status, 2-hour and permission rules**: HTTP tests, one per edge case, frozen clock.
- **Concurrent cancels**: N real processes.
- **Screens**: component tests for the dialog, the customer sheet, banners, the "too late" sentence.

## A2. Manual scenarios

| # | Steps | Expected | Result |
| --- | --- | --- | --- |
| M1 | Advisor opens a CONFIRMED booking → Cancel appointment | The dialog names the right bay, technician, time | PASS (verified via `CancelAppointmentModal.test.tsx` & manual run) |
| M2 | Pick "Customer is busy" → Cancel appointment | Cancelled badge; closed banner; no more buttons | PASS (verified via `CancelAppointmentTest::test_AC_07_1_cancel_frees_the_slot_at_once` & modal test) |
| M3 | New booking at the same time, same technician | Books successfully | PASS (verified via `CancelAppointmentTest::test_AC_07_1_cancel_frees_the_slot_at_once`) |
| M4 | Two tabs on one booking: tab 1 cancels (or changes it once F09/F12 exist); tab 2 cancels | Tab 2 gets 200 if tab 1 cancelled; the "changed by someone else" alert if tab 1 changed something else | PASS (verified via `ParallelCancelTest`, `test_AC_07_2_cancelling_twice_returns_current_state` & `CancelAppointmentModal.test.tsx` 412 alert) |
| M5 | Customer opens a booking more than 2 hours away → Cancel appointment → "Something came up" | Banner "You cancelled this appointment" | PASS (verified via `BookingDetailsPage.test.tsx`) |
| M6 | Customer opens a booking less than 2 hours away | No button; "Please call 028 3812 3456 to cancel." | PASS (verified via `BookingDetailsPage.test.tsx::test_AC_07_13_TC_07_16`) |

## A3. Test data

`World`, `AppointmentFactory`; clock 2026-11-02 08:00 +07; bookings at 10:00 (2 hours left) and 09:00 (1 hour left).

## A4. Not tested

Sending the cancellation SMS (F11).

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
| AC-07.1, FR-07.1, INV-07.1 | TC-07-01 |
| AC-07.2, FR-07.2, D-07.4 | TC-07-02 |
| AC-07.3, BR-07.1, INV-07.2 | TC-07-03, TC-07-12 |
| AC-07.4, BR-07.2, INV-07.3, D-07.3 | TC-07-04, TC-07-13 |
| AC-07.5 | TC-07-05 |
| AC-07.6, FR-07.4, D-07.1 | TC-07-06 |
| AC-07.7, INV-07.4 | TC-07-07 |
| AC-07.8, D-07.2 | TC-07-08 |
| AC-07.9, D-07.5, INV-07.5 | TC-07-09 |
| AC-07.10, D-07.6 | TC-07-10 |
| AC-07.11, FR-07.3, D-07.7 | TC-07-11 |
| AC-07.12 | TC-07-15 |
| AC-07.13 | TC-07-16 |
| INV-07.6 | TC-07-01 (columns checked) |
| API §B4 | TC-07-17 |
| Q-07.b | TC-07-18 |
| Same key resent | TC-07-14 |
| 412 in the dialog | TC-07-19 |

## B2. Test cases

Backend in `api/tests/Feature/Booking/Cancel/`.

| ID | Proves | Level | Given | When | Then | Fails if | Where |
| --- | --- | --- | --- | --- | --- | --- | --- |
| TC-07-01 | AC-07.1, INV-07.1, INV-07.6 | Integration | `onlyTechnicians(['An'])`; booking A 09:00 tomorrow | Advisor cancels A (`CUSTOMER_BUSY`, note); books B 09:00 | Cancel 200, `ETag "2"`, columns `cancel_reason`, `cancel_note`, `cancelled_at`, `cancelled_by_user_id` set; 0 active allocations of A; B 201 | Allocations not deactivated | `CancelAppointmentTest::test_AC_07_1_cancel_frees_the_slot_at_once` |
| TC-07-02 | AC-07.2, D-07.4 | Integration | Booking already cancelled, v2 | Cancel again with `If-Match: "2"` and with `"1"` | Both 200, `status CANCELLED`, `ETag "2"`; no new history row | Writes again or 412 | `…::test_AC_07_2_cancelling_twice_returns_current_state` |
| TC-07-03 | AC-07.3 | Integration | `COMPLETED` booking | Cancel | 422 `INVALID_STATUS_TRANSITION`, `currentStatus COMPLETED` | Allowed | `…::test_AC_07_3_completed_cannot_be_cancelled` |
| TC-07-04 | AC-07.4 | Integration | Clock 08:00; a customer's booking at 09:00 | The customer cancels | 422 `TOO_LATE_TO_CANCEL`, `dealershipPhone "028 3812 3456"`; still `CONFIRMED` | Time not checked | `…::test_AC_07_4_customer_too_late` |
| TC-07-05 | AC-07.5 | Integration | Booking at 08:30 | Advisor cancels | 200 | Customer rule applied to advisors | `…::test_AC_07_5_advisor_has_no_time_limit` |
| TC-07-06 | AC-07.6 | Integration | — | Advisor cancels without `reason` | 400 `VALIDATION_ERROR` on `reason` | Reason optional | `…::test_AC_07_6_staff_reason_required` |
| TC-07-07 | AC-07.7 | Integration | B's booking | A cancels | 404 `APPOINTMENT_NOT_FOUND` | 403/leak | `…::test_AC_07_7_cannot_cancel_another_customers_booking` |
| TC-07-08 | AC-07.8 | Integration | Foreman, admin | Cancel | 403 | Wrong Gate | `…::test_AC_07_8_foreman_and_admin_cannot_cancel` |
| TC-07-09 | AC-07.9 | Concurrency | Booking v1 | 5 processes cancel with `If-Match: "1"`, different keys | 5 × 200 `CANCELLED`; `version` = 2; 1 `CANCELLED` history row; 1 outbox `event_id` | 412 for the losers | `ParallelCancelTest::test_AC_07_9_parallel_cancels_all_succeed_once` |
| TC-07-10 | AC-07.10, D-07.6 | Integration | A `CONFIRMED` booking; a `HELD` booking (factory) | Cancel each (customer) | `CONFIRMED`: 1 `AppointmentCancelled` `event_id` (3 channels); `HELD`: 0 | Message for a hold | `…::test_AC_07_10_outbox_only_for_confirmed` |
| TC-07-11 | AC-07.11 | Integration | Booking v2 `CONFIRMED` | Cancel with `"1"`; cancel without `If-Match` | 412, body `appointment.version 2`, `ETag "2"`; 428 | Endpoint missing `if-match` | `…::test_AC_07_11_stale_and_missing_if_match` |
| TC-07-12 | INV-07.2 | Integration | Bookings in `CHECKED_IN`, `IN_PROGRESS`, `NO_SHOW`, `EXPIRED` | Cancel | 422 each | Wrong transition table | `…::test_INV_07_2_non_cancellable_statuses` (data provider) |
| TC-07-13 | INV-07.3, D-07.3 | Integration | Clock 08:00; bookings at 10:00 (exactly 2 hours), 09:59; a `HELD` at 08:30 | The customer cancels each | 200; 422; 200 | Wrong boundary; applied to `HELD` | `…::test_INV_07_3_two_hour_boundary` |
| TC-07-14 | F04 + F07 | Integration | Cancel with K → 200 | Resend K (the booking is now v2) | The saved 200, `Idempotent-Replayed: true` | 412 | `…::test_retry_with_same_key_replays` |
| TC-07-15 | AC-07.12, WEB-07.2 | Web component | Fake API: CONFIRMED booking (Bay 2, Trần Minh An, 09:00–10:00); cancel 200 | Open the dialog, pick a reason, confirm | "Bay 2 and technician Trần Minh An will be released for 09:00–10:00 …"; the request has `If-Match "1"`; then Cancelled badge, banner, no buttons | `If-Match` missing | `web/src/staff/appointments/CancelAppointmentModal.test.tsx` |
| TC-07-16 | AC-07.13, WEB-07.4 | Web component | Fake clock 08:00; booking 09:00 | Render customer details | No Cancel button; "It's less than 2 hours … call 028 3812 3456 to cancel." | Button shown | `web/src/customer/bookings/BookingDetailsPage.test.tsx` |
| TC-07-17 | API §B4 | Integration | — | Each row of the adversarial input table | As the table | A rule missing | `CancelAdversarialInputTest` |
| TC-07-18 | Q-07.b | Integration | Advisor cancels with a note | The customer `GET /appointments/{id}` | `cancellation.byRole = SERVICE_ADVISOR`, `reason null`, `note null` | Internal note leaked | `CancelAppointmentTest::test_customer_does_not_see_staff_note` |
| TC-07-19 | WEB-07.1, 412 | Web component | Fake API: cancel 412 with a rescheduled booking | Confirm the cancel | `AppointmentModifiedAlert` "Time: 09:00–10:00 → 14:00–15:00"; no automatic resend | Automatic retry | `CancelAppointmentModal.test.tsx` |
| TC-07-20 | All | Manual | Stack running | M1–M6 | As A2 | — | Record in A2 (All M1–M6 PASS) |

## B3. Fixtures

`AppointmentFactory` (F04) with `->status()`; `Parallel` (F04).

## B4. Regression

F04 (`TransitionTest`, `IfMatchTest`), F05 (`AppointmentDetailsPage.test.tsx`, `MyAppointmentsTest`), F06 (`AvailabilityTest`).
