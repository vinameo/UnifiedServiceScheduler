# 05: Appointment list screen

**What to build:** The staff Appointment list keeps date and filters in the URL, shows the table and loads the next page with "Load more" (AC-05.9).

**Blocked by:** [04-staff-day-list-api](04-staff-day-list-api.md)

**Status:** ready-for-agent

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-14

- [ ] TC-05-14 (AC-05.9; Web component): given Fake API with 2 pages; when Render `/staff/appointments`, pick a Technician, click "Load more"; then URL has `technicianId`; the request has `technicianId`; page 2 appended; footer "Showing 60 of 75 appointments…" (`meta.total` 75). Test: `web/src/staff/appointments/AppointmentListPage.test.tsx`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
