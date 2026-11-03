# 03: Appointment history and closed details

**What to build:** `GET /appointments/{appointment}/history` returns the history rows for staff only (`can:staff`, D-05.4, INV-05.7). Details shows the history, the "This appointment is closed…" banner for closed statuses and the masked phone with "Show number" (AC-05.10, D-05.7).

**Blocked by:** [02-appointment-details-for-staff](02-appointment-details-for-staff.md)

**Status:** done

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-13, TC-05-15

- [x] TC-05-13 (D-05.4, INV-05.7; Integration): given A booking created then `touch`ed (F04 test route); when Advisor `GET /history`; the owning customer `GET /history`; then 2 rows (`CREATED`, …) in time order, no phone/email; customer 403. Test: `AppointmentHistoryTest::test_D_05_4_history_is_staff_only`.
- [x] TC-05-15 (AC-05.10, D-05.7; Web component): given Fake API: a `CANCELLED` booking + history; when Render details; click "Show number"; then Closed banner; History has "Cancelled · Minh Anh"; the full number shows. Test: `…/AppointmentDetailsPage.test.tsx`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
