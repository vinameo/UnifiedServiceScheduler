# 09: Workshop board API

**What to build:** `GET /dealerships/{d}/workshop-board?date=` returns bays with blocks, technicians with level and booked minutes, and an overrun alert, scoped to the dealership (AC-12.12, INV-12.10).

**Blocked by:** [08-extend-retries-on-a-race](08-extend-retries-on-a-race.md)

**Status:** ready-for-agent

**Branch:** `feature/12-day-of-service`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-12-16, TC-12-17

- [ ] TC-12-16 (AC-12.12; Integration): given Today: blocks in K1, K3; X IN_PROGRESS ended 20 min ago; a cancelled booking; when `GET workshop-board?date=today`; then Bays with blocks (cancelled hidden); X `overrunMinutes 20`; alert `OVERRUN` for X with `nextBooking`; technicians: active only, with `level`, `bookedMinutes` and `status` (`OVERRUN` for X's technician). Test: `WorkshopBoardTest::test_AC_12_12_board`.
- [ ] TC-12-17 (INV-12.10; Integration): given Foreman of D1; a D2 booking; D2; when Check in, extend the D2 booking; open D2's board; then 404 `APPOINTMENT_NOT_FOUND` twice; 404 `DEALERSHIP_NOT_FOUND`. Test: `WorkshopScopeTest`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
