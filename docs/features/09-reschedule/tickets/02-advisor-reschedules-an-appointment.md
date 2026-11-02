# 02: Advisor reschedules an appointment (tracer bullet)

**What to build:** `POST /appointments/{appointment}/reschedule` with `If-Match` moves a booking to a new start through `moveTo`; when the new time is taken the old booking stays as it was (AC-09.1), it never clashes with itself (AC-09.2), and it writes one `RESCHEDULED` history row and one `AppointmentRescheduled` event (AC-09.11). Staff details gets the Reschedule screen with the "Change to …" summary (AC-09.12).

**Blocked by:** [01-prefactor-confirm-moveto-and-excludeappointmentid-are](01-prefactor-confirm-moveto-and-excludeappointmentid-are.md)

**Status:** ready-for-agent

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-01, TC-09-02, TC-09-15, TC-09-16

- [ ] TC-09-01 (AC-09.1, INV-09.1; Integration): given Booking X 09:00 (An, K1) v1; `onlyTechnicians(['An'])`; An busy 14:00–15:00 with another booking; when Reschedule X to 14:00 with `If-Match "1"`; then 409 `NO_AVAILABILITY`; X row identical (start, end, technician, bay, version 1); X's allocations unchanged and active. Test: `RescheduleTest::test_AC_09_1_failed_move_keeps_the_original`.
- [ ] TC-09-02 (AC-09.2, INV-09.3; Integration): given X 10:00–11:00 on An + K2; K2 free 11:00–11:30; when Reschedule to 10:30; then 200; X 10:30–11:30, still K2; overlap query 0. Test: `…::test_AC_09_2_move_inside_own_slot`.
- [ ] TC-09-15 (AC-09.11; Integration): given X v1; when Reschedule to 14:00; then 1 `RESCHEDULED` history row with `from`/`to`; 1 `AppointmentRescheduled` `event_id` (3 channels); `reference` unchanged; on a failed move none of these. Test: `…::test_AC_09_11_history_and_outbox`.
- [ ] TC-09-16 (AC-09.12; Web component): given Fake API: appointment, availability, reschedule 200; when Pick 14:00 on ReschedulePage, confirm; then "Change to Mon 2 Nov, 14:00–15:00"; request has `If-Match` and `keepTechnician: true`; navigates to details with "Appointment moved to …". Test: `web/src/staff/appointments/ReschedulePage.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
- [ ] Run `/code-review` on the branch and fix findings before later tickets build on it
