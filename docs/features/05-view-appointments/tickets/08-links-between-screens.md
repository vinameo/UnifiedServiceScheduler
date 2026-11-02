# 08: Links between screens

**What to build:** Add the D-05.8 links: from the list and confirmations to details, and back.

**Blocked by:** [07-my-bookings-and-customer-details-screens](07-my-bookings-and-customer-details-screens.md)

**Status:** ready-for-agent

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-17

- [ ] TC-05-17 (D-05.8; Web component): given Staff confirmed page; `VEHICLE_ALREADY_BOOKED` card with `existingAppointment`; when Render; then "View appointment" → `/staff/appointments/{id}`; "View existing booking" → the right id. Test: `web/src/staff/booking/BookingConfirmedPage.test.tsx`, `BookingErrorCard.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
