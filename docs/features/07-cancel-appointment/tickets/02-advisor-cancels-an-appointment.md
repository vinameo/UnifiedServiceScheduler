# 02: Advisor cancels an appointment (tracer bullet)

**What to build:** `POST /appointments/{appointment}/cancel` with `If-Match` lets an advisor cancel with a `CancelReason`; the slot is free at once for another booking (AC-07.1, INV-07.1, INV-07.6). Staff details gets the cancel dialog; after confirming the page shows the Cancelled badge and closed banner (AC-07.12).

**Blocked by:** [01-prefactor-customerchangepolicy](01-prefactor-customerchangepolicy.md)

**Status:** ready-for-agent

**Branch:** `feature/07-cancel-appointment`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-07-01, TC-07-05, TC-07-15

- [ ] TC-07-01 (AC-07.1, INV-07.1, INV-07.6; Integration): given `onlyTechnicians(['An'])`; booking A 09:00 tomorrow; when Advisor cancels A (`CUSTOMER_BUSY`, note); books B 09:00; then Cancel 200, `ETag "2"`, columns `cancel_reason`, `cancel_note`, `cancelled_at`, `cancelled_by_user_id` set; 0 active allocations of A; B 201. Test: `CancelAppointmentTest::test_AC_07_1_cancel_frees_the_slot_at_once`.
- [ ] TC-07-05 (AC-07.5; Integration): given Booking at 08:30; when Advisor cancels; then 200. Test: `…::test_AC_07_5_advisor_has_no_time_limit`.
- [ ] TC-07-15 (AC-07.12, WEB-07.2; Web component): given Fake API: CONFIRMED booking (Bay 2, Trần Minh An, 09:00–10:00); cancel 200; when Open the dialog, pick a reason, confirm; then "Bay 2 and technician Trần Minh An will be released for 09:00–10:00 …"; the request has `If-Match "1"`; then Cancelled badge, banner, no buttons. Test: `web/src/staff/appointments/CancelAppointmentModal.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
- [ ] Run `/code-review` on the branch and fix findings before later tickets build on it
