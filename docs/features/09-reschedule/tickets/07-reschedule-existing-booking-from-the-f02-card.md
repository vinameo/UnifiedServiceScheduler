# 07: Reschedule existing booking from the F02 card

**What to build:** The `VEHICLE_ALREADY_BOOKED` card on New booking offers "Reschedule existing booking" (D-09.11).

**Blocked by:** [06-customer-reschedules-api](06-customer-reschedules-api.md)

**Status:** ready-for-agent

**Branch:** `feature/09-reschedule`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-09-20

- [ ] TC-09-20 (D-09.11; Web component): given `VEHICLE_ALREADY_BOOKED` with a CONFIRMED `existingAppointment` (advisor); when Render the card; then "Reschedule existing booking" → `/staff/appointments/{id}/reschedule`. Test: `web/src/shared/booking/BookingErrorCard.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
