# 07: My bookings and customer details screens

**What to build:** The customer sees My bookings with Upcoming and Past tabs and opens a booking's details (WEB-05.4).

**Blocked by:** [06-my-bookings-api](06-my-bookings-api.md)

**Status:** ready-for-agent

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-18

- [ ] TC-05-18 (WEB-05.4; Web component): given Fake API: Upcoming 1, Past 0; when Render `/my-bookings`, switch to Past; then Card "Mon 2 Nov · 10:00–11:00" + "Confirmed"; Past: "No past bookings yet."; URL `?tab=past`. Test: `web/src/customer/bookings/MyBookingsPage.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
