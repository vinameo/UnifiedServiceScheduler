# 04: Confirm rules

**What to build:** Confirming after expiry answers 410 `HOLD_EXPIRED`; a vehicle already booked answers 409 and the hold stays `HELD`; only the owning customer with their own vehicle may confirm; the expiry boundary is exact (AC-10.2, AC-10.8, INV-10.4, INV-10.7, INV-10.8).

**Blocked by:** [03-hold-rules](03-hold-rules.md)

**Status:** ready-for-agent

**Branch:** `feature/10-temporary-hold`

**Read first:** [RQ](../1-requirement-spec.md) §B3 invariants, §B5–B9 for the IDs below; [API](../4-api-spec.md); [TC](../6-test-case.md) rows below.

**Test cases:** TC-10-02, TC-10-09, TC-10-10, TC-10-11

- [ ] TC-10-02 (AC-10.2; Integration): given Hold created at 08:00; when `travel(6)->minutes()`; confirm with `If-Match "1"`; then 410 `HOLD_EXPIRED`; status `EXPIRED`; allocations inactive. Test: `ConfirmHoldTest::test_AC_10_2_expired_hold_cannot_be_confirmed`.
- [ ] TC-10-09 (AC-10.8; Integration): given V has another booking at the held time; when Confirm with V; then 409 `VEHICLE_ALREADY_BOOKED`; hold still `HELD`, version 1. Test: `…::test_AC_10_8_vehicle_clash_keeps_the_hold`.
- [ ] TC-10-10 (INV-10.7; Integration): given A's hold; when B confirms it; advisor confirms it; A confirms with B's vehicle; then 404 `APPOINTMENT_NOT_FOUND`; 403; 404 `VEHICLE_NOT_FOUND`. Test: `…::test_INV_10_7_only_the_owner_confirms`.
- [ ] TC-10-11 (INV-10.4, INV-10.8; Integration): given Hold expiring at 08:05:00; when Confirm at 08:04:59; another hold confirmed at exactly 08:05:00; resend the first confirm with the same key; then 200; 410; the saved 200. Test: `…::test_boundary_and_replay`.
- [ ] README §8 checks green; no existing test skipped, deleted or weakened
