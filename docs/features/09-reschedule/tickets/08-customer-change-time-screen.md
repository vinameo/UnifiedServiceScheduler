# 08: Customer Change time screen

**What to build:** Customer details gets "Change time"; the Change time screen shows the booking's own slot as selectable and handles 412 by showing the latest data (AC-09.13, AC-09.3 web).

**Blocked by:** [07-reschedule-existing-booking-from-the-f02-card](07-reschedule-existing-booking-from-the-f02-card.md)

**Status:** ready-for-agent

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-17, TC-09-18

- [ ] TC-09-17 (AC-09.3 (web); Web component): given Fake API: reschedule 412 with a cancelled booking; when Confirm new time (customer); then `AppointmentModifiedAlert` "Status: Confirmed → Cancelled"; no automatic resend. Test: `web/src/customer/bookings/ChangeTimePage.test.tsx`.
- [ ] TC-09-18 (AC-09.13; Web component): given Fake availability with `excludeAppointmentId` → own slot free; when Render ChangeTimePage; then The availability request has `excludeAppointmentId`; own slot shows "Current". Test: `…/ChangeTimePage.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
