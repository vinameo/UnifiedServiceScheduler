# 08: Dealership details and opening hours

**What to build:** `GET /dealerships/{d}` (API-01.2) with its opening hours, and the Opening hours tab, grouping weekdays with the same hours (`groupWeekdays`, WEB-01.4). Every dealership-scoped route now answers 404 for another dealership (AC-01.2).

**Blocked by:** [07-bays-and-technicians](07-bays-and-technicians.md)

**Status:** ready-for-agent

**Branch:** `feature/01-master-data`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [WEB](../5-web-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-01-02, TC-01-12, TC-01-14

- [ ] TC-01-02 (AC-01.2, INV-01.3; Integration): given Admin of D1; D2 has data; when Admin calls the 4 routes of D2; then Each route 404 `DEALERSHIP_NOT_FOUND`. Test: `…/MasterData/DealershipScopeTest::test_AC_01_2_other_dealership_is_404` (data provider per route).
- [ ] TC-01-12 (AC-01.6; Web component): given Same; when Open Technicians, Opening hours; then "Level 6"; "Not taking bookings"; no "Shift" or "Skills" column; "Monday to Friday · 08:00–17:00"; "Sunday · Closed"; "UTC+7". Test: `…MasterDataPage.test.tsx`.
- [ ] TC-01-14 (WEB-01.4; Unit (web)): given Day sets; when `groupWeekdays`; then `[1..6 08–17]` → "Mon–Sat"; `[1,2,4]` → "Mon–Tue, Thu"; different hours split rows. Test: `web/src/shared/format/weekdays.test.ts`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
