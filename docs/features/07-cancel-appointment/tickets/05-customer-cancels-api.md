# 05: Customer cancels (API)

**What to build:** A customer cancels their own booking until 2 hours before start (`TOO_LATE_TO_CANCEL` with `dealershipPhone` after), gets 404 for someone else's, and a `HELD` booking writes no outbox event (AC-07.4, AC-07.7, AC-07.10, INV-07.3, D-07.3, D-07.6).

**Blocked by:** [04-versions-history-and-replay-on-cancel](04-versions-history-and-replay-on-cancel.md)

**Status:** done

**Branch:** `feature/07-cancel-appointment`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-07-04, TC-07-07, TC-07-10, TC-07-13, TC-07-18

- [x] TC-07-04 (AC-07.4; Integration): given Clock 08:00; a customer's booking at 09:00; when The customer cancels; then 422 `TOO_LATE_TO_CANCEL`, `dealershipPhone "028 3812 3456"`; still `CONFIRMED`. Test: `…::test_AC_07_4_customer_too_late`.
- [x] TC-07-07 (AC-07.7; Integration): given B's booking; when A cancels; then 404 `APPOINTMENT_NOT_FOUND`. Test: `…::test_AC_07_7_cannot_cancel_another_customers_booking`.
- [x] TC-07-10 (AC-07.10, D-07.6; Integration): given A `CONFIRMED` booking; a `HELD` booking (factory); when Cancel each (customer); then `CONFIRMED`: 1 `AppointmentCancelled` `event_id` (3 channels); `HELD`: 0. Test: `…::test_AC_07_10_outbox_only_for_confirmed`.
- [x] TC-07-13 (INV-07.3, D-07.3; Integration): given Clock 08:00; bookings at 10:00 (exactly 2 hours), 09:59; a `HELD` at 08:30; when The customer cancels each; then 200; 422; 200. Test: `…::test_INV_07_3_two_hour_boundary`.
- [x] TC-07-18 (Q-07.b; Integration): given Advisor cancels with a note; when The customer `GET /appointments/{id}`; then `cancellation.byRole = SERVICE_ADVISOR`, `reason null`, `note null`. Test: `CancelAppointmentTest::test_customer_does_not_see_staff_note`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
