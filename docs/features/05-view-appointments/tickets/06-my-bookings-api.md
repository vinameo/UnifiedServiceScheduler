# 06: My bookings API

**What to build:** `GET /me/appointments?scope=upcoming|past` returns only the customer's own bookings, never `HELD` ones, with cancelled and past ones under Past (AC-05.4, AC-05.8, D-05.3).

**Blocked by:** [05-appointment-list-screen](05-appointment-list-screen.md)

**Status:** done

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-06

- [x] TC-05-06 (AC-05.4, AC-05.8, D-05.3; Integration): given A: 2 future `CONFIRMED`, 1 `CONFIRMED` yesterday, 1 `CANCELLED` next week, 1 `HELD`, 1 `EXPIRED`; B: 3 bookings; when A calls `scope=upcoming`, `scope=past`; then Upcoming: exactly 2, earliest first; Past: yesterday's + the cancelled, latest first; no `HELD`/`EXPIRED`; none of B's. Test: `MyAppointmentsTest::test_AC_05_4_and_05_8_upcoming_and_past`.
- [x] README §8 checks green; no existing test skipped, deleted or weakened
