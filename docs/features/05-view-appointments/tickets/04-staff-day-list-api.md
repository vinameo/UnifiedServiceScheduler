# 04: Staff day list API

**What to build:** `GET /dealerships/{d}/appointments?date=` lists one local day with technician, bay and status filters, a cursor and `meta.total`, in a fixed number of queries (AC-05.2, AC-05.6, AC-05.7, INV-05.3–05.6).

**Blocked by:** [03-appointment-history-and-closed-details](03-appointment-history-and-closed-details.md)

**Status:** ready-for-agent

**Branch:** `feature/05-view-appointments`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-05-02, TC-05-07, TC-05-08, TC-05-09, TC-05-10, TC-05-12

- [ ] TC-05-02 (AC-05.2, INV-05.3; Integration): given Booking with `start_at = 2026-11-01T23:30:00Z` (06:30 on the 2nd local; inserted by the factory, ignoring opening hours); when Advisor `?date=2026-11-02`; `?date=2026-11-01`; then Present; absent. Test: `DealershipAppointmentsTest::test_AC_05_2_date_is_local_to_the_dealership`.
- [ ] TC-05-07 (AC-05.7, INV-05.4, INV-05.5; Integration): given 120 bookings that day, 10 with the same `start_at`; when `limit=50` following `nextCursor` to the end; then 50, 50, 20; the union is 120 distinct ids; order `(start, id)` ascending; last page `nextCursor: null`; `meta.total` = 120 on every page. Test: `DealershipAppointmentsTest::test_AC_05_7_cursor_paging_has_no_gaps_or_duplicates`.
- [ ] TC-05-08 (AC-05.6, D-05.2; Integration): given 5 bookings: An/K1 CONFIRMED, Dũng/K2 CHECKED_IN, An/K2 CANCELLED, EXPIRED, Chi/K3 COMPLETED; when Filter `technicianId=An`; `bayId=K2`; `status=CONFIRMED,CHECKED_IN`; no filter; then 2; 2; 2; 4 (no EXPIRED). Test: `…::test_AC_05_6_filters`.
- [ ] TC-05-09 (INV-05.6; Integration): given A technician of D2; when D1 advisor filters by that `technicianId`; then 200, `data: []`. Test: `…::test_INV_05_6_foreign_filter_returns_nothing`.
- [ ] TC-05-10 (AC-00.7; Integration): given Customer; when `GET /dealerships/{d}/appointments?date=…`; then 403 `FORBIDDEN`. Test: `…::test_AC_00_7_customer_cannot_list_staff_appointments`.
- [ ] TC-05-12 (DB §B2; Integration): given 5 then 50 bookings; when Count the list's queries; then Equal. Test: `DealershipAppointmentsTest::test_query_count_is_constant`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
